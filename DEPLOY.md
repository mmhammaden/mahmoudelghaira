# Deployment Guide — exercice7.web.app

This document describes how to deploy the Ghaira frontend to Firebase Hosting at `https://exercice7.web.app`.

## 1. What gets deployed

Only the **`/public/`** directory is deployed to Firebase Hosting. It contains static HTML + vanilla JS that talks directly to Supabase from the browser.

The Flask backend (`app.py`, `templates/`) is **not** deployed to Firebase Hosting — Firebase Hosting serves static files only.

## 2. Prerequisites

- Firebase CLI installed (`npm install -g firebase-tools`)
- Logged into Firebase (`firebase login`)
- The Firebase project `mahmoudelghaira` is the active/default project

## 3. Project / Hosting details

| Item | Value |
|------|-------|
| Firebase project ID | `mahmoudelghaira` |
| Hosting target name | `exercice7` |
| Site instance | `exercice7` |
| Live URL | `https://exercice7.web.app` |
| Local deploy folder | `/home/kali/Desktop/ghaira/public` |
| Supabase project (frontend backend) | `https://vjzogmivmohrygundsgk.supabase.co` |

## 4. Deployment command

From the project root:

```bash
cd /home/kali/Desktop/ghaira
firebase deploy --only hosting:exercice7 --project mahmoudelghaira
```

## 5. First-time setup (if target is not configured)

If the hosting target `exercice7` is not linked yet:

```bash
cd /home/kali/Desktop/ghaira
firebase target:apply hosting exercice7 exercice7
```

This writes the target mapping into `.firebaserc`.

## 6. Verify deployment

After deploy completes, check the live site:

```bash
curl -I https://exercice7.web.app
```

Expected: the site redirects `/` → `login.html` and shows the Ghaira login page.

## 7. Files involved

- `firebase.json` — hosting config (`public` dir, target `exercice7`, rewrite `**` → `/index.html`)
- `.firebaserc` — project + target mapping for `mahmoudelghaira`
- `public/` — all static files deployed to Firebase Hosting

## 8. Notes

- The frontend uses Supabase Auth (phone/password) and the Supabase anon key from `public/js/config.js`.
- No server-side backend runs on Firebase; the frontend calls Supabase directly.
- If you modify files in `public/`, re-run the deploy command above to push changes.
