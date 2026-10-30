# Deployment Guide — exercice7.web.app

## Components

- `public/` is deployed to Firebase Hosting.
- The Flask API (`app.py`) connects to the Neon PostgreSQL database.
- Neon stores classes, users, profiles, sessions, exercises, and results.

## Commands

```bash
# 1. Apply the Neon database schema
psql "$DATABASE_URL" -f backend/schema.sql

# 2. Deploy Firebase Hosting
firebase deploy --only hosting:exercice7 --project mahmoudelghaira

# 3. Deploy the Flask API to Google Cloud Run (connects to Neon)
gcloud run deploy ghaira-api \
  --source /home/kali/Desktop/ghaira/backend \
  --region europe-west1 \
  --allow-unauthenticated
```

## Verification

Register and log in through `https://exercice7.web.app` using an eight-digit
phone number beginning with 2, 3, or 4.

The authenticated API routes used by the frontend are:

- `GET /api/profile/me`
- `GET /api/exercises` (returns exercises for the student's class)
- `POST /api/exercises/<id>/submit` with `{ "answer": "A" }`
- `POST /api/auth/logout`

Create a production administrator by inserting a row directly into the Neon
`profiles` and `users` tables (phone must be 8 digits starting with 2, 3, or 4;
passwords are stored as PBKDF2-SHA256 hashes).
