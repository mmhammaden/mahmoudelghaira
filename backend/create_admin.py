#!/usr/bin/env python3
"""Create an administrator in the Cloudflare D1 database."""

import argparse
import getpass
import hashlib
import os
import re
import secrets
import subprocess
import tempfile


PHONE_RE = re.compile(r"^[234]\d{7}$")
PASSWORD_ITERATIONS = 100_000


def sql_string(value):
    """Return a safely quoted SQLite string literal."""
    return "'" + value.replace("'", "''") + "'"


def hash_password(password):
    salt = secrets.token_hex(16)
    digest = hashlib.pbkdf2_hmac(
        "sha256", password.encode("utf-8"), bytes.fromhex(salt), PASSWORD_ITERATIONS
    ).hex()
    return f"pbkdf2_sha256${PASSWORD_ITERATIONS}${salt}${digest}"


def create_admin(database, remote):
    print("Ghaira API - Admin Creation")
    print("=" * 40)
    name = input("Admin name: ").strip()
    phone = input("Admin phone: ").strip()
    password = getpass.getpass("Admin password: ")

    if not name or not phone or not password:
        raise SystemExit("Error: All fields are required.")
    if not PHONE_RE.fullmatch(phone):
        raise SystemExit("Error: Phone must be 8 digits and start with 2, 3 or 4.")
    if len(password) < 6:
        raise SystemExit("Error: Password must be at least 6 characters.")

    user_id = secrets.token_hex(16)
    hashed = hash_password(password)
    sql = (
        "INSERT INTO users (id, phone, password_hash) VALUES "
        f"({sql_string(user_id)}, {sql_string(phone)}, {sql_string(hashed)}); "
        "INSERT INTO profiles (id, name, phone, role, class_id) VALUES "
        f"({sql_string(user_id)}, {sql_string(name)}, {sql_string(phone)}, 'admin', NULL);"
    )

    sql_file = None
    try:
        with tempfile.NamedTemporaryFile(
            mode="w", prefix="ghaira-admin-", suffix=".sql", delete=False
        ) as handle:
            handle.write(sql)
            sql_file = handle.name
        os.chmod(sql_file, 0o600)

        command = ["npx", "wrangler", "d1", "execute", database]
        command.append("--remote" if remote else "--local")
        command.extend(["--file", sql_file])
        subprocess.run(command, check=True)
    except FileNotFoundError:
        raise SystemExit("Error: npx is not installed or is not on PATH.")
    except subprocess.CalledProcessError as error:
        raise SystemExit(
            f"Error: Wrangler could not create the administrator ({error.returncode})."
        )
    finally:
        if sql_file:
            try:
                os.unlink(sql_file)
            except FileNotFoundError:
                pass

    print("\nAdmin account created successfully!")
    print(f"Phone: {phone}")
    print(f"Name: {name}")
    print("Role: admin")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--database", default="ghaira-db")
    parser.add_argument(
        "--local",
        action="store_true",
        help="write to Wrangler's local D1 database instead of production",
    )
    args = parser.parse_args()
    create_admin(args.database, remote=not args.local)


if __name__ == "__main__":
    main()
