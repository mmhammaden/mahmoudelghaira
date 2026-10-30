# Phone-only migration

The application authenticates with Mauritanian phone numbers and passwords.

## Architecture

- Firebase Hosting serves the static frontend.
- The Flask API serves `/api/*` and connects to the Neon PostgreSQL database.
- Neon stores classes, users, profiles, sessions, exercises, and results.
- Phone numbers must contain eight digits and begin with 2, 3, or 4.
- Passwords are stored as salted PBKDF2-SHA256 hashes.
- Successful login returns an opaque session token stored in Neon.

## Deployment order

1. Apply `backend/schema.sql` to the target Neon database.
2. Deploy the Flask API to Google Cloud Run, connecting to Neon.
3. Set the API address in `public/js/api.js`.
4. Deploy Firebase Hosting.
5. Test registration, login, protected routes, and logout.

The schema migration recreates the application tables and therefore must only be
used when replacing the existing seed dataset is intended.
