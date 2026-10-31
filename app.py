import os
import re
import secrets
import psycopg2
from psycopg2.extras import DictCursor
from functools import wraps

from flask import (
    Flask,
    render_template,
    request,
    redirect,
    url_for,
    session,
    flash,
    g,
    jsonify,
)
from werkzeug.security import generate_password_hash, check_password_hash
from flask_cors import CORS

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "dev-secret-key-change-in-production")

CORS(app, origins=[
    "https://exercice7.web.app",
    "https://exercice7.firebaseapp.com",
], supports_credentials=True)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATABASE_URL = os.environ.get("DATABASE_URL", "postgresql://ghaira:ghaira@localhost:5433/ghaira")

# Phone rule: exactly 8 digits, starting with 2, 3 or 4. No verification of ownership.
PHONE_RE = re.compile(r"^[234]\d{7}$")


# --------------------------------------------------------------------------- #
# Database helpers
# --------------------------------------------------------------------------- #
def get_db():
    db = getattr(g, "_database", None)
    if db is None:
        db = g._database = psycopg2.connect(DATABASE_URL, cursor_factory=DictCursor)
    return db


@app.teardown_appcontext
def close_db(exception):
    db = getattr(g, "_database", None)
    if db is not None:
        db.close()


def _adapt(q):
    return re.sub(r"('(?:[^']*)'|\"(?:[^\"]*)\")|(\?)", lambda m: m.group(1) if m.group(1) else "%s", q)


def query_db(query, args=(), one=False, commit=False):
    query = _adapt(query)
    cur = get_db().cursor()
    cur.execute(query, args)
    if commit:
        get_db().commit()
        cur.close()
        return None
    rows = cur.fetchall()
    cur.close()
    return (rows[0] if rows else None) if one else rows


def execute_db(query, args=(), commit=False):
    """Execute a write/query SQL statement via psycopg2 cursor (adapts ? → %s)."""
    query = _adapt(query)
    cur = get_db().cursor()
    cur.execute(query, args)
    if commit:
        get_db().commit()
    cur.close()


def init_db():
    """Create tables and seed default data on first run."""
    with app.app_context():
        db = get_db()
        cur = db.cursor()

        # Create tables (split from executescript into individual statements)
        cur.execute(
            "CREATE TABLE IF NOT EXISTS classes ("
            "id SERIAL PRIMARY KEY, "
            "name TEXT NOT NULL UNIQUE)"
        )
        cur.execute(
            "CREATE TABLE IF NOT EXISTS users ("
            "id SERIAL PRIMARY KEY, "
            "name TEXT NOT NULL, "
            "phone TEXT NOT NULL UNIQUE, "
            "password_hash TEXT NOT NULL, "
            "role TEXT NOT NULL DEFAULT 'student', "
            "class_id INTEGER, "
            "created_at TEXT DEFAULT CURRENT_TIMESTAMP, "
            "FOREIGN KEY (class_id) REFERENCES classes(id))"
        )
        cur.execute(
            "CREATE TABLE IF NOT EXISTS exercises ("
            "id SERIAL PRIMARY KEY, "
            "title TEXT NOT NULL, "
            "question TEXT NOT NULL, "
            "option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT, "
            "correct_answer TEXT NOT NULL, "
            "class_id INTEGER NOT NULL, "
            "created_at TEXT DEFAULT CURRENT_TIMESTAMP, "
            "FOREIGN KEY (class_id) REFERENCES classes(id))"
        )
        cur.execute(
            "CREATE TABLE IF NOT EXISTS results ("
            "id SERIAL PRIMARY KEY, "
            "user_id INTEGER NOT NULL, "
            "exercise_id INTEGER NOT NULL, "
            "score INTEGER NOT NULL, "
            "total INTEGER NOT NULL, "
            "submitted_at TEXT DEFAULT CURRENT_TIMESTAMP, "
            "FOREIGN KEY (user_id) REFERENCES users(id), "
            "FOREIGN KEY (exercise_id) REFERENCES exercises(id))"
        )
        db.commit()
        cur.close()

        # Seed classes if empty — Algerian secondary school classes
        cur = db.cursor()
        cur.execute("SELECT COUNT(*) FROM classes")
        count = cur.fetchone()
        if count[0] == 0:
            cur.executemany(
                _adapt("INSERT INTO classes (name) VALUES (?)"),
                [
                    ("2AS maths",),
                    ("3AS PC",),
                    ("4AS PC",),
                    ("5D maths",),
                    ("5A maths",),
                    ("6A maths",),
                ],
            )
            db.commit()
        cur.close()

        # Seed default admin if none exists (admin / admin123)
        cur = db.cursor()
        cur.execute("SELECT id FROM users WHERE role = 'admin'")
        admin = cur.fetchone()
        if not admin:
            cur.execute(
                _adapt(
                    "INSERT INTO users (name, phone, password_hash, role, class_id) "
                    "VALUES (?, ?, ?, ?, ?)"
                ),
                ("المشرف العام", "31406022", generate_password_hash("admin123"), "admin", None),
            )
            db.commit()
        cur.close()

        # Seed sample exercises if empty
        cur = db.cursor()
        cur.execute("SELECT COUNT(*) FROM exercises")
        count = cur.fetchone()
        if count[0] == 0:
            cur.execute("SELECT id, name FROM classes ORDER BY id")
            classes = cur.fetchall()
            sample = [
                ("اختبار رياضيات 1", "كم equals 2 + 2؟", "3", "4", "5", "6", "B", classes[0]["id"]),
                ("اختبار رياضيات 2", "كم equals 5 × 6؟", "25", "30", "35", "40", "B", classes[0]["id"]),
                ("اختبار علوم 1", "أي كوكب يُعرف بالكوكب الأحمر؟", "الأرض", "المريخ", "المشتري", "الزهرة", "B", classes[1]["id"]),
                ("اختبار علوم 2", "H2O تُعرف عادة باسم؟", "الأكسجين", "الهيدروجين", "الماء", "الحمض", "C", classes[1]["id"]),
                ("اختبار لغة إنجليزية 1", "أي كلمة هي اسم؟", "Run", "Quickly", "Dog", "Happy", "C", classes[2]["id"]),
                ("اختبار لغة إنجليزية 2", "أي اسم يجمع؟", "Cat", "Child", "Mice", "Goose", "C", classes[2]["id"]),
            ]
            cur.executemany(
                _adapt(
                    "INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) "
                    "VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                ),
                sample,
            )
            db.commit()
        cur.close()


# --------------------------------------------------------------------------- #
# Auth helpers
# --------------------------------------------------------------------------- #
def login_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if "user_id" not in session:
            return redirect(url_for("login"))
        return view(*args, **kwargs)

    return wrapped


def admin_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if "user_id" not in session:
            return redirect(url_for("login"))
        if session.get("role") != "admin":
            flash("Admin access required.", "error")
            return redirect(url_for("dashboard"))
        return view(*args, **kwargs)

    return wrapped


def current_user():
    if "user_id" not in session:
        return None
    return query_db(
        "SELECT u.id, u.name, u.phone, u.role, c.name AS class_name "
        "FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.id = ?",
        [session["user_id"]],
        one=True,
    )


@app.context_processor
def inject_user():
    return dict(current_user=current_user())


# --------------------------------------------------------------------------- #
# Public routes
# --------------------------------------------------------------------------- #
@app.route("/")
def index():
    if "user_id" in session:
        return redirect(url_for("dashboard"))
    return redirect(url_for("login"))


@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        session.clear()
        phone = (request.form.get("phone") or "").strip()
        password = request.form.get("password") or ""
        user = query_db("SELECT * FROM users WHERE phone = ?", [phone], one=True)
        if user and check_password_hash(user["password_hash"], password):
            session["user_id"] = user["id"]
            session["name"] = user["name"]
            session["role"] = user["role"]
            session["class_id"] = user["class_id"]
            flash("Welcome back, " + user["name"] + "!", "success")
            return redirect(url_for("dashboard"))
        flash("Invalid phone number or password.", "error")
    if "user_id" in session:
        return redirect(url_for("dashboard"))
    return render_template("login.html")

# --------------------------------------------------------------------------- #
# Token store (in-memory — sufficient for free-tier single-instance Render)
# --------------------------------------------------------------------------- #
_tokens = {}  # token -> user_id

def _create_token(user_id):
    token = secrets.token_hex(32)
    _tokens[token] = user_id
    return token

def _get_user_from_token():
    auth = request.headers.get("Authorization", "")
    if auth.startswith("Bearer "):
        token = auth[7:]
        user_id = _tokens.get(token)
        if user_id:
            return query_db("SELECT * FROM users WHERE id = ?", [user_id], one=True)
    return None


# --------------------------------------------------------------------------- #
# JSON API routes (used by Firebase frontend)
# --------------------------------------------------------------------------- #
@app.route("/api/auth/login", methods=["POST"])
def api_auth_login():
    data = request.get_json(silent=True) or {}
    phone = (data.get("phone") or "").strip()
    password = data.get("password") or ""
    if not phone or not password:
        return jsonify(error="Missing phone or password"), 400
    user = query_db("SELECT * FROM users WHERE phone = ?", [phone], one=True)
    if user and check_password_hash(user["password_hash"], password):
        token = _create_token(user["id"])
        return jsonify(token=token, role=user["role"], name=user["name"])
    return jsonify(error="Invalid phone number or password."), 401


@app.route("/api/auth/register", methods=["POST"])
def api_auth_register():
    data = request.get_json(silent=True) or {}
    name = (data.get("name") or "").strip()
    phone = (data.get("phone") or "").strip()
    password = data.get("password") or ""
    class_id = data.get("class_id")

    if not name:
        return jsonify(error="Name is required."), 400
    if not PHONE_RE.match(phone):
        return jsonify(error="Phone number must be exactly 8 digits and start with 2, 3, or 4."), 400
    if not password or len(password) < 6:
        return jsonify(error="Password must be at least 6 characters."), 400
    if not class_id:
        return jsonify(error="Please select a class."), 400
    if query_db("SELECT id FROM users WHERE phone = ?", [phone], one=True):
        return jsonify(error="Phone number already exists."), 409

    execute_db(
        "INSERT INTO users (name, phone, password_hash, role, class_id) VALUES (?, ?, ?, ?, ?)",
        (name, phone, generate_password_hash(password), "student", int(class_id)),
        commit=True,
    )
    return jsonify(ok=True), 201


@app.route("/api/auth/logout", methods=["POST"])
def api_auth_logout():
    auth = request.headers.get("Authorization", "")
    if auth.startswith("Bearer "):
        _tokens.pop(auth[7:], None)
    return jsonify(ok=True)


@app.route("/api/profile/me")
def api_profile_me():
    user = _get_user_from_token()
    if not user:
        return jsonify(error="Unauthorized"), 401
    return jsonify(id=user["id"], name=user["name"], role=user["role"], class_id=user["class_id"])


@app.route("/api/classes")
def api_classes():
    classes = query_db("SELECT id, name FROM classes ORDER BY name")
    return jsonify(classes=[{"id": c["id"], "name": c["name"]} for c in classes])


# chapters = classes (no separate table — map class as chapter)
@app.route("/api/chapters")
def api_chapters():
    user = _get_user_from_token()
    if not user:
        return jsonify(error="Unauthorized"), 401
    class_id = request.args.get("class_id")
    if class_id:
        rows = query_db("SELECT id, name FROM classes WHERE id = ?", [int(class_id)])
    else:
        rows = query_db(
            "SELECT c.id, c.name, COUNT(e.id) AS exercise_count "
            "FROM classes c LEFT JOIN exercises e ON e.class_id = c.id "
            "WHERE c.id = ? GROUP BY c.id",
            [user["class_id"]]
        ) if user["role"] == "student" else query_db(
            "SELECT c.id, c.name, COUNT(e.id) AS exercise_count "
            "FROM classes c LEFT JOIN exercises e ON e.class_id = c.id "
            "GROUP BY c.id ORDER BY c.name"
        )
    return jsonify(chapters=[{"id": r["id"], "title": r["name"],
                               "description": "", "exercise_count": r.get("exercise_count", 0)} for r in rows])


@app.route("/api/exercises")
def api_exercises():
    user = _get_user_from_token()
    if not user:
        return jsonify(error="Unauthorized"), 401
    chapter_id = request.args.get("chapter_id")  # chapter_id == class_id
    class_id = chapter_id if chapter_id else user.get("class_id")
    rows = query_db("SELECT * FROM exercises WHERE class_id = ? ORDER BY id", [class_id])
    return jsonify(exercises=[dict(r) for r in rows])


@app.route("/api/exercises/<int:exercise_id>")
def api_exercise_get(exercise_id):
    user = _get_user_from_token()
    if not user:
        return jsonify(error="Unauthorized"), 401
    ex = query_db("SELECT * FROM exercises WHERE id = ?", [exercise_id], one=True)
    if not ex:
        return jsonify(error="Not found"), 404
    d = dict(ex)
    d["chapter_id"] = ex["class_id"]  # frontend uses chapter_id
    return jsonify(d)


@app.route("/api/exercises/<int:exercise_id>/submit", methods=["POST"])
def api_exercise_submit(exercise_id):
    user = _get_user_from_token()
    if not user:
        return jsonify(error="Unauthorized"), 401
    ex = query_db("SELECT * FROM exercises WHERE id = ?", [exercise_id], one=True)
    if not ex:
        return jsonify(error="Not found"), 404
    data = request.get_json(silent=True) or {}
    answer = data.get("answer", "")
    correct = answer == ex["correct_answer"]
    execute_db(
        "INSERT INTO results (user_id, exercise_id, score, total) VALUES (?, ?, ?, ?)",
        [user["id"], exercise_id, 1 if correct else 0, 1],
        commit=True,
    )
    return jsonify(correct=correct)


@app.route("/api/results")
def api_results():
    user = _get_user_from_token()
    if not user:
        return jsonify(error="Unauthorized"), 401
    rows = query_db(
        "SELECT r.id, r.exercise_id, e.title AS exercise_title, r.score, r.total, r.submitted_at "
        "FROM results r JOIN exercises e ON r.exercise_id = e.id "
        "WHERE r.user_id = ? ORDER BY r.submitted_at DESC",
        [user["id"]]
    )
    return jsonify(results=[dict(r) for r in rows])


# --------------------------------------------------------------------------- #
# Admin API routes
# --------------------------------------------------------------------------- #
def _require_admin():
    user = _get_user_from_token()
    if not user or user["role"] != "admin":
        return None
    return user


@app.route("/api/admin/users")
def api_admin_users():
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    rows = query_db(
        "SELECT u.id, u.name, u.phone, u.role, u.class_id, c.name AS class_name "
        "FROM users u LEFT JOIN classes c ON u.class_id = c.id ORDER BY u.role DESC, u.name"
    )
    return jsonify(users=[dict(r) for r in rows])


@app.route("/api/admin/users/<int:user_id>/role", methods=["PUT"])
def api_admin_user_role(user_id):
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    data = request.get_json(silent=True) or {}
    role = data.get("role")
    if role not in ("admin", "student"):
        return jsonify(error="Invalid role"), 400
    execute_db("UPDATE users SET role = ? WHERE id = ?", [role, user_id], commit=True)
    return jsonify(ok=True)


@app.route("/api/admin/users/<int:user_id>/class", methods=["PUT"])
def api_admin_user_class(user_id):
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    data = request.get_json(silent=True) or {}
    class_id = data.get("class_id")
    execute_db("UPDATE users SET class_id = ? WHERE id = ?",
               [int(class_id) if class_id else None, user_id], commit=True)
    return jsonify(ok=True)


@app.route("/api/admin/users/<int:user_id>", methods=["DELETE"])
def api_admin_user_delete(user_id):
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    execute_db("DELETE FROM results WHERE user_id = ?", [user_id], commit=True)
    execute_db("DELETE FROM users WHERE id = ?", [user_id], commit=True)
    return jsonify(ok=True)


@app.route("/api/admin/exercises")
def api_admin_exercises():
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    rows = query_db(
        "SELECT e.*, c.name AS class_name FROM exercises e "
        "JOIN classes c ON e.class_id = c.id ORDER BY e.id DESC"
    )
    return jsonify(exercises=[dict(r) for r in rows])


@app.route("/api/admin/exercises/<int:exercise_id>")
def api_admin_exercise_get(exercise_id):
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    ex = query_db("SELECT * FROM exercises WHERE id = ?", [exercise_id], one=True)
    if not ex:
        return jsonify(error="Not found"), 404
    d = dict(ex)
    d["chapter_id"] = ex["class_id"]
    return jsonify(d)


@app.route("/api/admin/exercises", methods=["POST"])
def api_admin_exercise_create():
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    data = request.get_json(silent=True) or {}
    class_id = data.get("chapter_id") or data.get("class_id")
    execute_db(
        "INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) "
        "VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        (data.get("title"), data.get("question"), data.get("option_a"), data.get("option_b"),
         data.get("option_c"), data.get("option_d"), data.get("correct_answer"), int(class_id)),
        commit=True,
    )
    return jsonify(ok=True), 201


@app.route("/api/admin/exercises/<int:exercise_id>", methods=["PUT"])
def api_admin_exercise_update(exercise_id):
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    data = request.get_json(silent=True) or {}
    class_id = data.get("chapter_id") or data.get("class_id")
    execute_db(
        "UPDATE exercises SET title=?, question=?, option_a=?, option_b=?, option_c=?, option_d=?, "
        "correct_answer=?, class_id=? WHERE id=?",
        (data.get("title"), data.get("question"), data.get("option_a"), data.get("option_b"),
         data.get("option_c"), data.get("option_d"), data.get("correct_answer"), int(class_id), exercise_id),
        commit=True,
    )
    return jsonify(ok=True)


@app.route("/api/admin/exercises/<int:exercise_id>", methods=["DELETE"])
def api_admin_exercise_delete(exercise_id):
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    execute_db("DELETE FROM results WHERE exercise_id = ?", [exercise_id], commit=True)
    execute_db("DELETE FROM exercises WHERE id = ?", [exercise_id], commit=True)
    return jsonify(ok=True)


@app.route("/api/admin/results")
def api_admin_results():
    if not _require_admin():
        return jsonify(error="Unauthorized"), 401
    rows = query_db(
        "SELECT r.id, r.user_id, r.exercise_id, u.name AS student_name, u.class_id, "
        "c.name AS class_name, e.title AS exercise_title, r.score, r.total, r.submitted_at "
        "FROM results r JOIN users u ON r.user_id = u.id "
        "LEFT JOIN classes c ON u.class_id = c.id "
        "JOIN exercises e ON r.exercise_id = e.id ORDER BY r.submitted_at DESC"
    )
    return jsonify(results=[dict(r) for r in rows])


@app.route("/register", methods=["GET", "POST"])
def register():
    if "user_id" in session:
        return redirect(url_for("dashboard"))
    classes = query_db("SELECT * FROM classes ORDER BY name")
    if request.method == "POST":
        name = (request.form.get("name") or "").strip()
        phone = (request.form.get("phone") or "").strip()
        password = request.form.get("password") or ""
        class_id = request.form.get("class_id") or None

        errors = []
        if not name:
            errors.append("Name is required.")
        if not PHONE_RE.match(phone):
            errors.append("Phone number must be exactly 8 digits and start with 2, 3, or 4.")
        if not password or len(password) < 6:
            errors.append("Password must be at least 6 characters.")
        if not class_id:
            errors.append("Please select a class.")
        if query_db("SELECT id FROM users WHERE phone = ?", [phone], one=True):
            errors.append("A user with this phone number already exists.")

        if errors:
            for error in errors:
                flash(error, "error")
            return render_template(
                "register.html", classes=classes, name=name, phone=phone, class_id=class_id
            )

        execute_db(
            "INSERT INTO users (name, phone, password_hash, role, class_id) "
            "VALUES (?, ?, ?, ?, ?)",
            (name, phone, generate_password_hash(password), "student", int(class_id)),
            commit=True,
        )
        flash("Registration successful. Please log in.", "success")
        return redirect(url_for("login"))

    return render_template("register.html", classes=classes)


@app.route("/logout")
def logout():
    session.clear()
    flash("You have been logged out.", "info")
    return redirect(url_for("login"))


# --------------------------------------------------------------------------- #
# Dashboard
# --------------------------------------------------------------------------- #
@app.route("/dashboard")
@login_required
def dashboard():
    if session.get("role") == "admin":
        return redirect(url_for("admin_dashboard"))
    return redirect(url_for("student_exercises"))



# --------------------------------------------------------------------------- #
# Admin routes
# --------------------------------------------------------------------------- #
@app.route("/admin")
@login_required
@admin_required
def admin_dashboard():
    stats = {
        "students": query_db("SELECT COUNT(*) FROM users WHERE role = 'student'")[0][0],
        "classes": query_db("SELECT COUNT(*) FROM classes")[0][0],
        "exercises": query_db("SELECT COUNT(*) FROM exercises")[0][0],
        "results": query_db("SELECT COUNT(*) FROM results")[0][0],
    }
    recent = query_db(
        """
        SELECT r.id, u.name AS student_name, c.name AS class_name,
               e.title AS exercise_title, r.score, r.total, r.submitted_at
        FROM results r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN classes c ON u.class_id = c.id
        JOIN exercises e ON r.exercise_id = e.id
        ORDER BY r.submitted_at DESC
        LIMIT 10
        """
    )
    return render_template("admin_dashboard.html", stats=stats, recent=recent)


@app.route("/admin/programs")
@login_required
@admin_required
def admin_programs():
    exercises = query_db(
        """
        SELECT e.id, e.title, e.question, e.correct_answer, c.name AS class_name
        FROM exercises e
        JOIN classes c ON e.class_id = c.id
        ORDER BY e.id DESC
        """
    )
    return render_template("admin_programs.html", exercises=exercises)



@app.route("/admin/programs/add", methods=["GET", "POST"])
@login_required
@admin_required
def admin_program_add():
    classes = query_db("SELECT * FROM classes ORDER BY name")
    if request.method == "POST":
        title = (request.form.get("title") or "").strip()
        question = (request.form.get("question") or "").strip()
        option_a = request.form.get("option_a") or ""
        option_b = request.form.get("option_b") or ""
        option_c = request.form.get("option_c") or ""
        option_d = request.form.get("option_d") or ""
        correct = request.form.get("correct_answer") or ""
        class_id = request.form.get("class_id") or None

        errors = []
        if not title:
            errors.append("Title is required.")
        if not question:
            errors.append("Question is required.")
        if correct not in ("A", "B", "C", "D"):
            errors.append("Please select the correct answer.")
        if not class_id:
            errors.append("Please select a class.")
        if errors:
            for error in errors:
                flash(error, "error")
            return render_template(
                "admin_program_form.html",
                classes=classes,
                exercise={
                    "title": title,
                    "question": question,
                    "option_a": option_a,
                    "option_b": option_b,
                    "option_c": option_c,
                    "option_d": option_d,
                    "correct_answer": correct,
                },
            )

        execute_db(
            "INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            (title, question, option_a, option_b, option_c, option_d, correct, int(class_id)),
            commit=True,
        )
        flash("Exercise added.", "success")
        return redirect(url_for("admin_programs"))

    return render_template("admin_program_form.html", classes=classes, exercise=None)



@app.route("/admin/programs/<int:exercise_id>/edit", methods=["GET", "POST"])
@login_required
@admin_required
def admin_program_edit(exercise_id):
    exercise = query_db("SELECT * FROM exercises WHERE id = ?", [exercise_id], one=True)
    if not exercise:
        flash("Exercise not found.", "error")
        return redirect(url_for("admin_programs"))
    classes = query_db("SELECT * FROM classes ORDER BY name")

    if request.method == "POST":
        title = (request.form.get("title") or "").strip()
        question = (request.form.get("question") or "").strip()
        option_a = request.form.get("option_a") or ""
        option_b = request.form.get("option_b") or ""
        option_c = request.form.get("option_c") or ""
        option_d = request.form.get("option_d") or ""
        correct = request.form.get("correct_answer") or ""
        class_id = request.form.get("class_id") or None

        errors = []
        if not title:
            errors.append("Title is required.")
        if not question:
            errors.append("Question is required.")
        if correct not in ("A", "B", "C", "D"):
            errors.append("Please select the correct answer.")
        if not class_id:
            errors.append("Please select a class.")
        if errors:
            for error in errors:
                flash(error, "error")
            return render_template(
                "admin_program_form.html",
                classes=classes,
                exercise={**exercise, "title": title, "question": question,
                          "option_a": option_a, "option_b": option_b,
                          "option_c": option_c, "option_d": option_d,
                          "correct_answer": correct},
            )

        execute_db(
            "UPDATE exercises SET title=?, question=?, option_a=?, option_b=?, "
            "option_c=?, option_d=?, correct_answer=?, class_id=? WHERE id=?",
            (title, question, option_a, option_b, option_c, option_d, correct, int(class_id), exercise_id),
            commit=True,
        )
        flash("Exercise updated.", "success")
        return redirect(url_for("admin_programs"))

    return render_template("admin_program_form.html", classes=classes, exercise=exercise)


@app.route("/admin/programs/<int:exercise_id>/delete", methods=["POST"])
@login_required
@admin_required
def admin_program_delete(exercise_id):
    execute_db("DELETE FROM results WHERE exercise_id = ?", [exercise_id], commit=True)
    execute_db("DELETE FROM exercises WHERE id = ?", [exercise_id], commit=True)
    flash("Exercise deleted.", "success")
    return redirect(url_for("admin_programs"))



@app.route("/admin/users")
@login_required
@admin_required
def admin_users():
    users = query_db(
        """
        SELECT u.id, u.name, u.phone, u.role, c.name AS class_name
        FROM users u
        LEFT JOIN classes c ON u.class_id = c.id
        ORDER BY u.role DESC, u.name
        """
    )
    classes = query_db("SELECT * FROM classes ORDER BY name")
    return render_template("admin_users.html", users=users, classes=classes)


@app.route("/admin/users/<int:user_id>/role", methods=["POST"])
@login_required
@admin_required
def admin_user_role(user_id):
    new_role = request.form.get("role")
    if new_role not in ("admin", "student"):
        flash("Invalid role.", "error")
        return redirect(url_for("admin_users"))
    if new_role == "student":
        admin_count = query_db("SELECT COUNT(*) FROM users WHERE role = 'admin'")[0][0]
        target = query_db("SELECT role FROM users WHERE id = ?", [user_id], one=True)
        if admin_count <= 1 and target and target["role"] == "admin":
            flash("Cannot demote the only administrator.", "error")
            return redirect(url_for("admin_users"))
    execute_db("UPDATE users SET role = ? WHERE id = ?", [new_role, user_id], commit=True)
    flash("User role updated.", "success")
    return redirect(url_for("admin_users"))


@app.route("/admin/users/<int:user_id>/class", methods=["POST"])
@login_required
@admin_required
def admin_user_class(user_id):
    class_id = request.form.get("class_id") or None
    execute_db(
        "UPDATE users SET class_id = ? WHERE id = ?", [int(class_id) if class_id else None, user_id],
        commit=True,
    )
    flash("User class updated.", "success")
    return redirect(url_for("admin_users"))


@app.route("/admin/users/<int:user_id>/delete", methods=["POST"])
@login_required
@admin_required
def admin_user_delete(user_id):
    user = query_db("SELECT role FROM users WHERE id = ?", [user_id], one=True)
    if not user:
        flash("User not found.", "error")
        return redirect(url_for("admin_users"))
    if user["role"] == "admin":
        admin_count = query_db("SELECT COUNT(*) FROM users WHERE role = 'admin'")[0][0]
        if admin_count <= 1:
            flash("Cannot delete the only administrator.", "error")
            return redirect(url_for("admin_users"))
    execute_db("DELETE FROM results WHERE user_id = ?", [user_id], commit=True)
    execute_db("DELETE FROM users WHERE id = ?", [user_id], commit=True)
    flash("User deleted.", "success")
    return redirect(url_for("admin_users"))



@app.route("/admin/results")
@login_required
@admin_required
def admin_results():
    class_id = request.args.get("class_id") or None
    user_id = request.args.get("user_id") or None

    query = """
        SELECT r.id, u.name AS student_name, c.name AS class_name,
               e.title AS exercise_title, r.score, r.total, r.submitted_at
        FROM results r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN classes c ON u.class_id = c.id
        JOIN exercises e ON r.exercise_id = e.id
    """
    args = []
    conditions = []
    if class_id:
        conditions.append("u.class_id = ?")
        args.append(int(class_id))
    if user_id:
        conditions.append("u.id = ?")
        args.append(int(user_id))
    if conditions:
        query += " WHERE " + " AND ".join(conditions)
    query += " ORDER BY r.submitted_at DESC"

    results = query_db(query, args)
    classes = query_db("SELECT * FROM classes ORDER BY name")
    students = query_db(
        "SELECT id, name FROM users WHERE role = 'student' ORDER BY name"
    )
    return render_template(
        "admin_results.html",
        results=results,
        classes=classes,
        students=students,
        sel_class=class_id,
        sel_user=user_id,
    )



# --------------------------------------------------------------------------- #
# Student routes
# --------------------------------------------------------------------------- #
@app.route("/student/exercises")
@login_required
def student_exercises():
    if session.get("role") == "admin":
        return redirect(url_for("admin_dashboard"))
    class_id = session.get("class_id")
    exercises = query_db(
        "SELECT * FROM exercises WHERE class_id = ? ORDER BY id DESC", [class_id]
    )
    done = {
        row["exercise_id"]
        for row in query_db(
            "SELECT exercise_id FROM results WHERE user_id = ?", [session["user_id"]]
        )
    }
    return render_template("student_exercises.html", exercises=exercises, done=done)


@app.route("/student/exercise/<int:exercise_id>", methods=["GET", "POST"])
@login_required
def student_take_exercise(exercise_id):
    if session.get("role") == "admin":
        return redirect(url_for("admin_dashboard"))
    exercise = query_db(
        "SELECT * FROM exercises WHERE id = ? AND class_id = ?",
        [exercise_id, session.get("class_id")],
        one=True,
    )
    if not exercise:
        flash("Exercise not available for your class.", "error")
        return redirect(url_for("student_exercises"))

    if request.method == "POST":
        answer = request.form.get("answer") or ""
        correct = 1 if answer == exercise["correct_answer"] else 0
        execute_db(
            "INSERT INTO results (user_id, exercise_id, score, total) VALUES (?, ?, ?, ?)",
            [session["user_id"], exercise_id, correct, 1],
            commit=True,
        )
        flash(
            "Result saved! Your answer was " + ("correct." if correct else "incorrect."),
            "success",
        )
        return redirect(url_for("student_exercises"))

    return render_template("student_take.html", exercise=exercise)


@app.route("/student/results")
@login_required
def student_results():
    if session.get("role") == "admin":
        return redirect(url_for("admin_dashboard"))
    results = query_db(
        """
        SELECT r.id, e.title AS exercise_title, r.score, r.total, r.submitted_at
        FROM results r
        JOIN exercises e ON r.exercise_id = e.id
        WHERE r.user_id = ?
        ORDER BY r.submitted_at DESC
        """,
        [session["user_id"]],
    )
    return render_template("student_results.html", results=results)



# --------------------------------------------------------------------------- #
# Admin API — Neon PostgreSQL Auth
# --------------------------------------------------------------------------- #
# Delete a user account via Neon PostgreSQL.
# Removes the user's profile, results, and any associated data.

@app.route('/api/admin/users/delete', methods=['POST'])
def admin_user_delete_neon():
    user = _get_user_from_token()
    if not user or user["role"] != "admin":
        return jsonify(error="Unauthorized"), 401
    data = request.get_json(silent=True) or {}
    user_id = data.get('user_id')
    if not user_id:
        return jsonify(error='Missing user_id'), 400
    execute_db("DELETE FROM results WHERE user_id = ?", [user_id], commit=True)
    execute_db("DELETE FROM users WHERE id = ?", [user_id], commit=True)
    return jsonify(ok=True)

# --------------------------------------------------------------------------- #
# Run
# --------------------------------------------------------------------------- #
init_db()

if __name__ == "__main__":
    app.run(debug=True, host="0.0.0.0", port=5000)

