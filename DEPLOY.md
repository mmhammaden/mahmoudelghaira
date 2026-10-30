# Deployment Guide — exercice7.web.app

## Components

- `public/` is deployed to Firebase Hosting.
- `src/worker.py` is deployed as the Cloudflare Worker `ghaira-api`.
- `ghaira-db` is the D1 database bound to the Worker as `ghaira_db`.

## Commands

```bash
npx wrangler d1 execute ghaira-db --remote --file cloudflare/reset-phone-only.sql
npx wrangler deploy
firebase deploy --only hosting:exercice7 --project mahmoudelghaira
```

## Verification

Verify the Worker health endpoint first, then register and log in through
`https://exercice7.web.app` using an eight-digit phone number beginning with
2, 3, or 4.

The authenticated API routes used by the frontend are:

- `GET /api/profile/me`
- `GET /api/exercises` (returns exercises for the student's class)
- `POST /api/exercises/<id>/submit` with `{ "answer": "A" }`
- `POST /api/auth/logout`

Create a production administrator with:

```bash
python3 backend/create_admin.py
```

The script writes to the remote `ghaira-db` D1 database through Wrangler.
