import hashlib
import hmac
import os
import re
import secrets
import time

from flask import Flask, jsonify, request
from workers import wsgi
from pyodide.ffi import run_sync, to_js

app = Flask(__name__)

FRONTEND_ORIGIN = os.environ.get("FRONTEND_ORIGIN", "https://exercice7.web.app")


def get_database():
    return request.environ["workers.env"].ghaira_db


def query_all(sql, *values):
    statement = get_database().prepare(sql)
    if values:
        statement = statement.bind(*values)
    result = run_sync(statement.all())
    if isinstance(result, dict):
        return result.get("results", [])
    return getattr(result, "results", [])


def query_one(sql, *values):
    statement = get_database().prepare(sql)
    if values:
        statement = statement.bind(*values)
    return run_sync(statement.first())


def execute(sql, *values):
    statement = get_database().prepare(sql)
    if values:
        statement = statement.bind(*values)
    return run_sync(statement.run())


@app.after_request
def add_cors_headers(response):
    response.headers["Access-Control-Allow-Origin"] = FRONTEND_ORIGIN
    response.headers["Access-Control-Allow-Headers"] = "Authorization, Content-Type"
    response.headers["Access-Control-Allow-Methods"] = "GET, POST, PUT, DELETE, OPTIONS"
    response.headers["Vary"] = "Origin"
    return response


@app.route("/api/<path:path>", methods=["OPTIONS"])
def preflight(path):
    return "", 204


@app.get("/api/health")
def health():
    return jsonify({"status": "ok", "service": "ghaira-api"})


@app.get("/api/classes")
def list_classes():
    rows = query_all("SELECT id, name FROM classes ORDER BY name")
    return jsonify({"classes": rows or []})


@app.get("/api/chapters")
def list_chapters():
    user_id, err = require_auth()
    if err:
        return err

    profile = query_one("SELECT role, class_id FROM profiles WHERE id = ?", user_id)
    if not profile:
        return jsonify({"error": "Profile not found"}), 404

    class_id = profile["class_id"]
    if profile["role"] == "admin" and request.args.get("class_id"):
        class_id = int(request.args["class_id"])
    if not class_id:
        return jsonify({"chapters": []})

    rows = query_all(
        """
        SELECT ch.id, ch.title, ch.description, ch.position,
               COUNT(DISTINCT e.id) AS exercise_count,
               COUNT(DISTINCT r.exercise_id) AS completed_count
        FROM chapters ch
        LEFT JOIN exercises e ON e.chapter_id = ch.id
        LEFT JOIN results r ON r.exercise_id = e.id AND r.user_id = ?
        WHERE ch.class_id = ?
        GROUP BY ch.id, ch.title, ch.description, ch.position
        ORDER BY ch.position, ch.id
        """,
        user_id,
        class_id,
    )
    return jsonify({"chapters": rows or []})


PHONE_RE = re.compile(r"^[234]\d{7}$")
PASSWORD_ITERATIONS = 100_000
WEB_CRYPTO_MAX_PBKDF2_ITERATIONS = 100_000
SESSION_TTL_SECONDS = 30 * 24 * 60 * 60


def pbkdf2_sha256(password, salt, iterations):
    """PBKDF2-HMAC-SHA256 compatible with Cloudflare's Python runtime.

    Cloudflare Workers currently omits hashlib.pbkdf2_hmac, so use its native
    Web Crypto implementation there. This avoids expensive Python-level loops.
    """
    native_pbkdf2 = getattr(hashlib, "pbkdf2_hmac", None)
    if native_pbkdf2:
        return native_pbkdf2("sha256", password, salt, iterations)

    if iterations > WEB_CRYPTO_MAX_PBKDF2_ITERATIONS:
        raise ValueError("PBKDF2 iteration count is unsupported by Web Crypto")

    from js import Object, Uint8Array, crypto

    def as_js(value):
        return to_js(value, dict_converter=Object.fromEntries)

    key = run_sync(
        crypto.subtle.importKey(
            "raw", as_js(password), "PBKDF2", False, as_js(["deriveBits"])
        )
    )
    algorithm = as_js(
        {
            "name": "PBKDF2",
            "hash": "SHA-256",
            "salt": salt,
            "iterations": iterations,
        }
    )
    bits = run_sync(crypto.subtle.deriveBits(algorithm, key, 256))
    return bytes(Uint8Array.new(bits).to_py())


def hash_password(password):
    salt = secrets.token_hex(16)
    digest = pbkdf2_sha256(
        password.encode("utf-8"), bytes.fromhex(salt), PASSWORD_ITERATIONS
    ).hex()
    return f"pbkdf2_sha256${PASSWORD_ITERATIONS}${salt}${digest}"


def check_password(password, stored):
    try:
        algorithm, iterations, salt, expected = stored.split("$", 3)
        if algorithm != "pbkdf2_sha256":
            return False
        actual = pbkdf2_sha256(
            password.encode("utf-8"), bytes.fromhex(salt), int(iterations)
        ).hex()
        return hmac.compare_digest(actual, expected)
    except (AttributeError, TypeError, ValueError):
        return False


def verify_token():
    auth_header = request.headers.get("Authorization", "")
    if not auth_header.startswith("Bearer "):
        return None
    token = auth_header.split(" ", 1)[1].strip()
    if not token:
        return None
    now = int(time.time())
    row = query_one(
        "SELECT user_id FROM sessions WHERE token = ? AND expires_at > ?",
        token,
        now,
    )
    if row:
        return row.get("user_id")
    return None


def require_auth():
    user_id = verify_token()
    if not user_id:
        return None, (jsonify({"error": "Unauthorized"}), 401)
    return user_id, None


def require_admin(user_id):
    row = query_one("SELECT role FROM profiles WHERE id = ?", user_id)
    if not row or row.get("role") != "admin":
        return (jsonify({"error": "Forbidden: administrator access required"}), 403)
    return None


@app.post("/api/auth/register")
def register():
    body = request.get_json(silent=True) or {}
    name = (body.get("name") or "").strip()
    phone = (body.get("phone") or "").strip()
    password = body.get("password") or ""
    class_id = body.get("class_id")

    if not name or not phone or not password or not class_id:
        return jsonify({"error": "name, phone, password and class_id are required"}), 400
    if not PHONE_RE.fullmatch(phone):
        return jsonify({"error": "Phone must be 8 digits and start with 2, 3 or 4"}), 400
    if len(password) < 6:
        return jsonify({"error": "Password must be at least 6 characters"}), 400
    if not query_one("SELECT id FROM classes WHERE id = ?", int(class_id)):
        return jsonify({"error": "Class not found"}), 400
    if query_one("SELECT id FROM users WHERE phone = ?", phone):
        return jsonify({"error": "Phone number already exists"}), 409

    user_id = secrets.token_hex(16)
    execute(
        "INSERT INTO users (id, phone, password_hash) VALUES (?, ?, ?)",
        user_id, phone, hash_password(password),
    )
    try:
        execute(
            "INSERT INTO profiles (id, name, phone, role, class_id) VALUES (?, ?, ?, 'student', ?)",
            user_id, name, phone, int(class_id),
        )
    except Exception:
        execute("DELETE FROM users WHERE id = ?", user_id)
        raise
    return jsonify({"ok": True}), 201


@app.post("/api/auth/login")
def login():
    body = request.get_json(silent=True) or {}
    phone = (body.get("phone") or "").strip()
    password = body.get("password") or ""
    user = query_one("SELECT id, password_hash FROM users WHERE phone = ?", phone)
    if not user or not check_password(password, user.get("password_hash")):
        return jsonify({"error": "Invalid phone number or password"}), 401

    token = secrets.token_urlsafe(32)
    now = int(time.time())
    execute("DELETE FROM sessions WHERE expires_at <= ?", now)
    execute(
        "INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)",
        token, user["id"], now + SESSION_TTL_SECONDS,
    )
    profile = query_one(
        "SELECT id, name, phone, role, class_id FROM profiles WHERE id = ?",
        user["id"],
    )
    return jsonify({"token": token, "profile": profile})


@app.post("/api/auth/logout")
def logout():
    auth_header = request.headers.get("Authorization", "")
    if auth_header.startswith("Bearer "):
        execute("DELETE FROM sessions WHERE token = ?", auth_header.split(" ", 1)[1].strip())
    return jsonify({"ok": True})


@app.get("/api/profile/me")
def get_my_profile():
    user_id = verify_token()
    if not user_id:
        return jsonify({"error": "Unauthorized"}), 401
    row = query_one(
        "SELECT id, name, phone, role, class_id FROM profiles WHERE id = ?",
        user_id,
    )
    if not row:
        return jsonify({"error": "Profile not found"}), 404
    return jsonify(row)


@app.get("/api/exercises")
def list_exercises():
    user_id, err = require_auth()
    if err:
        return err

    profile = query_one("SELECT role, class_id FROM profiles WHERE id = ?", user_id)
    if not profile:
        return jsonify({"error": "Profile not found"}), 404

    if profile["role"] == "admin":
        return jsonify({"error": "Admins use /api/admin/exercises"}), 403

    chapter_id = request.args.get("chapter_id", type=int)
    sql = """
        SELECT e.id, e.title, e.question, e.option_a, e.option_b, e.option_c,
               e.option_d, e.class_id, e.chapter_id, ch.title AS chapter_title
        FROM exercises e
        LEFT JOIN chapters ch ON ch.id = e.chapter_id
        WHERE e.class_id = ?
    """
    values = [profile["class_id"]]
    if chapter_id is not None:
        sql += " AND e.chapter_id = ?"
        values.append(chapter_id)
    sql += " ORDER BY e.id"
    rows = query_all(sql, *values)
    return jsonify({"exercises": rows or []})


@app.get("/api/exercises/<int:exercise_id>")
def get_exercise(exercise_id):
    user_id, err = require_auth()
    if err:
        return err

    profile = query_one("SELECT role, class_id FROM profiles WHERE id = ?", user_id)
    if not profile:
        return jsonify({"error": "Profile not found"}), 404

    if profile["role"] == "admin":
        row = query_one(
            """
            SELECT id, title, question, option_a, option_b, option_c, option_d,
                   correct_answer, class_id, chapter_id
            FROM exercises
            WHERE id = ?
            """,
            exercise_id,
        )
    else:
        row = query_one(
            """
            SELECT id, title, question, option_a, option_b, option_c, option_d,
                   class_id, chapter_id
            FROM exercises
            WHERE id = ? AND class_id = ?
            """,
            exercise_id,
            profile["class_id"],
        )

    if not row:
        return jsonify({"error": "Exercise not found"}), 404
    return jsonify(row)


@app.post("/api/exercises/<int:exercise_id>/submit")
def submit_exercise(exercise_id):
    user_id, err = require_auth()
    if err:
        return err

    profile = query_one("SELECT role, class_id FROM profiles WHERE id = ?", user_id)
    if not profile:
        return jsonify({"error": "Profile not found"}), 404
    if profile["role"] != "student":
        return jsonify({"error": "Students only"}), 403

    body = request.get_json(silent=True) or {}
    answer = body.get("answer")
    if answer not in ("A", "B", "C", "D"):
        return jsonify({"error": "Invalid answer"}), 400

    exercise = query_one(
        "SELECT correct_answer FROM exercises WHERE id = ? AND class_id = ?",
        exercise_id,
        profile["class_id"],
    )
    if not exercise:
        return jsonify({"error": "Exercise not found for your class"}), 404

    correct = 1 if answer == exercise["correct_answer"] else 0

    try:
        execute(
            "INSERT INTO results (user_id, exercise_id, score, total) VALUES (?, ?, ?, 1)",
            user_id,
            exercise_id,
            correct,
        )
    except Exception:
        return jsonify({"error": "You have already submitted this exercise"}), 409

    return jsonify({"ok": True, "correct": bool(correct), "score": correct})


@app.get("/api/results")
def list_my_results():
    user_id, err = require_auth()
    if err:
        return err

    rows = query_all(
        """
        SELECT r.id, r.exercise_id, e.title AS exercise_title,
               r.score, r.total, r.submitted_at
        FROM results r
        JOIN exercises e ON r.exercise_id = e.id
        WHERE r.user_id = ?
        ORDER BY r.submitted_at DESC
        """,
        user_id,
    )
    return jsonify({"results": rows or []})


@app.get("/api/admin/exercises")
def admin_list_exercises():
    user_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(user_id)
    if admin_err:
        return admin_err

    rows = query_all(
        """
        SELECT e.id, e.title, e.question, e.option_a, e.option_b, e.option_c,
               e.option_d, e.correct_answer, e.class_id, e.chapter_id,
               c.name AS class_name, ch.title AS chapter_title
        FROM exercises e
        JOIN classes c ON e.class_id = c.id
        LEFT JOIN chapters ch ON e.chapter_id = ch.id
        ORDER BY e.id DESC
        """
    )
    return jsonify({"exercises": rows or []})


@app.get("/api/admin/exercises/<int:exercise_id>")
def admin_get_exercise(exercise_id):
    user_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(user_id)
    if admin_err:
        return admin_err
    row = query_one(
        """
        SELECT id, title, question, option_a, option_b, option_c, option_d,
               correct_answer, class_id, chapter_id
        FROM exercises WHERE id = ?
        """,
        exercise_id,
    )
    if not row:
        return jsonify({"error": "Exercise not found"}), 404
    return jsonify(row)


@app.post("/api/admin/exercises")
def admin_create_exercise():
    user_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(user_id)
    if admin_err:
        return admin_err

    body = request.get_json(silent=True) or {}
    title = body.get("title")
    question = body.get("question")
    option_a = body.get("option_a")
    option_b = body.get("option_b")
    option_c = body.get("option_c")
    option_d = body.get("option_d")
    correct = body.get("correct_answer")
    class_id = body.get("class_id")
    chapter_id = body.get("chapter_id")

    if not title or not question:
        return jsonify({"error": "title and question are required"}), 400
    if correct not in ("A", "B", "C", "D"):
        return jsonify({"error": "correct_answer must be A, B, C or D"}), 400
    if not class_id:
        return jsonify({"error": "class_id is required"}), 400
    if not chapter_id:
        return jsonify({"error": "chapter_id is required"}), 400
    chapter = query_one(
        "SELECT id FROM chapters WHERE id = ? AND class_id = ?",
        int(chapter_id),
        int(class_id),
    )
    if not chapter:
        return jsonify({"error": "Chapter does not belong to this class"}), 400

    result = execute(
        """
        INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id, chapter_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        title, question, option_a or "", option_b or "", option_c or "", option_d or "", correct, int(class_id), int(chapter_id),
    )
    exercise_id = result.meta.get("last_row_id") if result.meta else None
    return jsonify({"ok": True, "id": exercise_id}), 201


@app.put("/api/admin/exercises/<int:exercise_id>")
def admin_update_exercise(exercise_id):
    user_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(user_id)
    if admin_err:
        return admin_err

    body = request.get_json(silent=True) or {}
    fields = []
    values = []
    for col in ("title", "question", "option_a", "option_b", "option_c", "option_d",
                "correct_answer", "class_id", "chapter_id"):
        if col in body:
            fields.append(f"{col} = ?")
            values.append(body[col])
    if not fields:
        return jsonify({"error": "No fields to update"}), 400
    if "correct_answer" in body and body["correct_answer"] not in ("A", "B", "C", "D"):
        return jsonify({"error": "correct_answer must be A, B, C or D"}), 400

    values.append(exercise_id)
    execute(
        f"UPDATE exercises SET {', '.join(fields)} WHERE id = ?",
        *values,
    )
    return jsonify({"ok": True})


@app.delete("/api/admin/exercises/<int:exercise_id>")
def admin_delete_exercise(exercise_id):
    user_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(user_id)
    if admin_err:
        return admin_err

    execute("DELETE FROM results WHERE exercise_id = ?", exercise_id)
    execute("DELETE FROM exercises WHERE id = ?", exercise_id)
    return jsonify({"ok": True})


@app.get("/api/admin/results")
def admin_list_results():
    user_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(user_id)
    if admin_err:
        return admin_err

    rows = query_all(
        """
        SELECT r.id, r.score, r.total, r.submitted_at,
               p.name AS student_name, p.class_id, c.name AS class_name,
               e.title AS exercise_title
        FROM results r
        JOIN profiles p ON r.user_id = p.id
        LEFT JOIN classes c ON p.class_id = c.id
        JOIN exercises e ON r.exercise_id = e.id
        ORDER BY r.submitted_at DESC
        """
    )
    return jsonify({"results": rows or []})


@app.get("/api/admin/users")
def admin_list_users():
    user_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(user_id)
    if admin_err:
        return admin_err

    rows = query_all(
        """
        SELECT p.id, p.name, p.phone, p.role, p.class_id, c.name AS class_name
        FROM profiles p
        LEFT JOIN classes c ON p.class_id = c.id
        ORDER BY p.role DESC, p.name
        """
    )
    return jsonify({"users": rows or []})


@app.put("/api/admin/users/<user_id>/role")
def admin_update_user_role(user_id):
    requester_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(requester_id)
    if admin_err:
        return admin_err

    body = request.get_json(silent=True) or {}
    new_role = body.get("role")
    if new_role not in ("student", "admin"):
        return jsonify({"error": "Invalid role"}), 400

    target = query_one("SELECT role FROM profiles WHERE id = ?", user_id)
    if not target:
        return jsonify({"error": "User not found"}), 404

    if new_role == "student" and target["role"] == "admin":
        admin_count = query_one("SELECT COUNT(*) AS n FROM profiles WHERE role = 'admin'")
        if admin_count and admin_count["n"] <= 1:
            return jsonify({"error": "Cannot demote the only administrator"}), 400

    execute("UPDATE profiles SET role = ? WHERE id = ?", new_role, user_id)
    return jsonify({"ok": True})


@app.put("/api/admin/users/<user_id>/class")
def admin_update_user_class(user_id):
    requester_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(requester_id)
    if admin_err:
        return admin_err

    body = request.get_json(silent=True) or {}
    class_id = body.get("class_id")

    target = query_one("SELECT id FROM profiles WHERE id = ?", user_id)
    if not target:
        return jsonify({"error": "User not found"}), 404

    execute("UPDATE profiles SET class_id = ? WHERE id = ?", class_id, user_id)
    return jsonify({"ok": True})


@app.delete("/api/admin/users/<user_id>")
def admin_delete_user(user_id):
    requester_id, err = require_auth()
    if err:
        return err
    admin_err = require_admin(requester_id)
    if admin_err:
        return admin_err

    target = query_one("SELECT role FROM profiles WHERE id = ?", user_id)
    if not target:
        return jsonify({"error": "User not found"}), 404
    if target["role"] == "admin":
        admin_count = query_one("SELECT COUNT(*) AS n FROM profiles WHERE role = 'admin'")
        if admin_count and admin_count["n"] <= 1:
            return jsonify({"error": "Cannot delete the only administrator"}), 400

    execute("DELETE FROM results WHERE user_id = ?", user_id)
    execute("DELETE FROM sessions WHERE user_id = ?", user_id)
    execute("DELETE FROM profiles WHERE id = ?", user_id)
    execute("DELETE FROM users WHERE id = ?", user_id)
    return jsonify({"ok": True})


Default = wsgi.entrypoint(app)
