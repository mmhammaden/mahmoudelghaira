# Deployment Guide — Render + Firebase + Neon

## Architecture

- `public/` is deployed to **Firebase Hosting** (static frontend).
- The Flask API (`app.py`) is deployed to **Render** (web service).
- **Neon** PostgreSQL stores classes, users, profiles, sessions, exercises, and results.
- Phone numbers must contain eight digits and begin with 2, 3, or 4.
- Passwords are stored as salted PBKDF2-SHA256 hashes.

## Prerequisites

- A [Render](https://render.com) account (free plan OK).
- A [Neon](https://neon.com) project with a database.
- A [Firebase](https://firebase.google.com) project for Hosting.

## Step 1 — Deploy the API on Render

1. Connect your GitHub repository to Render.
2. Render detects `render.yaml` at the root.
3. Add the environment variables in the Render dashboard:
   - `DATABASE_URL` = your Neon connection string (with `sslmode=require`)
   - `SECRET_KEY` = a random string (e.g. `python3 -c "import secrets; print(secrets.token_hex(32))"`)
4. Render will build and deploy. Note your service URL, e.g. `https://ghaira-api-xxxx.onrender.com`.

## Step 2 — Configure the frontend

Edit `public/js/api.js` and set `API_BASE_URL` to your Render service URL:

```js
const API_BASE_URL = 'https://ghaira-api-xxxx.onrender.com';
```

## Step 3 — Deploy Firebase Hosting

```bash
cd /home/kali/Desktop/ghaira
firebase deploy --only hosting:exercice7 --project mahmoudelghaira
```

## Step 4 — Initialize the database

On first deploy, `init_db()` in `app.py` creates the tables and seeds default data (classes, admin user, sample exercises) automatically when the app receives its first request.

Default admin: phone `31406022`, password `admin123`.

## Verification

- Open your Firebase Hosting URL (e.g. `https://exercice7.web.app`).
- Register with an 8-digit phone number starting with 2, 3, or 4.
- Log in and test exercises and results.

