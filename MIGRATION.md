# Phone-only migration

The application authenticates with Mauritanian phone numbers and passwords.

## Architecture

- Firebase Hosting serves the static frontend.
- A Cloudflare Python Worker serves `/api/*`.
- D1 stores classes, users, profiles, sessions, exercises, and results.
- Phone numbers must contain eight digits and begin with 2, 3, or 4.
- Passwords are stored as salted PBKDF2-SHA256 hashes.
- Successful login returns an opaque session token stored in D1.

## Deployment order

1. Apply `cloudflare/reset-phone-only.sql` to the target D1 database.
2. Deploy the Worker with `npx wrangler deploy`.
3. Set the Worker address in `public/js/api.js`.
4. Deploy Firebase Hosting.
5. Test registration, login, protected routes, and logout.

The reset migration recreates the application tables and therefore must only be
used when replacing the existing seed dataset is intended.
