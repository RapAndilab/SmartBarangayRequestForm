# Smart Barangay Request Form

A barangay document-request system with two parts:

- **`BarangayPython/`** — Django REST API backend (the main app). Handles users,
  document requests, face verification (DeepFace), email + SMS notifications, and
  document generation.
- **`Barangay_Eforms/`** — PHP web frontend that calls the Django API.

## Running the Django backend locally

```powershell
cd BarangayPython
.\venv\Scripts\Activate.ps1
cd barangaypython
copy .env.example .env   # then edit .env and fill in real secrets
python manage.py migrate
python manage.py runserver
```

- API: http://localhost:8000/api/
- Admin: http://localhost:8000/admin/

### Configuration

All secrets are read from environment variables (see `.env.example`). For local
development, copy it to `.env` and fill in values — `core/settings.py` loads that
file automatically. **Never commit `.env`.**

## Running the PHP frontend

`Barangay_Eforms/` needs a PHP server (e.g. XAMPP/Apache). It expects the Django
API to be running at `http://127.0.0.1:8000`.

---

## ⚠️ Deployment notes (please read before deploying to Vercel)

Vercel is a **serverless/static** host, and this Django app does not fit that model
well as-is. Two hard blockers:

1. **Size:** The app imports `deepface`, which depends on **TensorFlow (~600 MB+)**.
   Vercel serverless functions have a **250 MB unzipped** limit, so the build will
   fail. To deploy on Vercel you'd have to remove face verification or move it to a
   separate service.
2. **Database & files:** Vercel's filesystem is **read-only and ephemeral**, so
   `db.sqlite3` and uploaded media/documents won't persist. You'd need an external
   database (e.g. Postgres on Neon/Supabase) and object storage (e.g. S3).

A `vercel.json` is included under `BarangayPython/barangaypython/` for completeness
(set the Vercel **Root Directory** to that folder), but expect the TensorFlow size
limit to block it.

### Deploying to Render (recommended)

A `render.yaml` blueprint is included at the repo root. It provisions a Postgres
database and a Python web service running gunicorn.

1. Push this repo to GitHub (already done).
2. On https://dashboard.render.com → **New +** → **Blueprint**, and select this repo.
3. Render reads `render.yaml` and creates the database + web service.
4. After the first deploy, open the web service → **Environment** tab and fill in the
   secret values (email, SMS, etc.) listed in `.env.example`. `DATABASE_URL` and
   `DJANGO_SECRET_KEY` are wired up automatically.
5. Create an admin user from the Render **Shell**: `python manage.py createsuperuser`

**Important — plan size:** This app loads TensorFlow (for DeepFace face verification),
which needs roughly **2 GB RAM**. Render's free/starter tiers (512 MB) will crash with
an out-of-memory error, so `render.yaml` uses the **Standard** plan. If you don't need
face verification, removing the `deepface`/`tensorflow` dependency would let it run on a
much smaller (even free) instance.
