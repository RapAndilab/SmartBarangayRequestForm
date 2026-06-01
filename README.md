# Smart Barangay Request Form

A barangay document-request system with two parts:

- **`BarangayPython/`** — Django REST API backend (the main app). Handles users,
  document requests, admin approval, email notifications, and document generation.
- **`Barangay_Eforms/`** — PHP web frontend that calls the Django API.

## Registration flow

1. A visitor fills out the registration form (profile photo is **optional**).
2. The account is created as **unverified** and a **6-digit code** is emailed to them.
3. They enter the code on the verify page (10-minute expiry, with a "resend" option).
4. Once verified, they can log in. **Login is blocked until the email is verified.**

## Document request flow

1. A logged-in resident fills out a document form (Clearance / Certification / Residency).
2. The request is created as **Pending** (`confirmed = False`).
3. An admin reviews it in the Django admin (`/admin/`) and **approves** it.
4. The resident is emailed and can then **download** the document. Downloads are blocked
   (HTTP 403) until the request is approved.

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

## Deploying to Render

A `render.yaml` blueprint is included at the repo root. It provisions a Postgres
database and a Python web service running gunicorn — and it runs on Render's **free
tier** (the app no longer bundles TensorFlow).

1. Push this repo to GitHub.
2. On https://dashboard.render.com → **New +** → **Blueprint**, and select this repo.
3. Render reads `render.yaml` and creates the database + web service. Click **Apply**.
4. After the first deploy, open the web service → **Environment** tab and fill in the
   secret values (email, etc.) listed in `.env.example`. `DATABASE_URL` and
   `DJANGO_SECRET_KEY` are wired up automatically.
5. Create an admin user from the Render **Shell**: `python manage.py createsuperuser`,
   then approve requests at `https://<your-app>.onrender.com/admin/`.

### Known limitation — file storage

On Render's **free tier the disk is ephemeral**, so generated `.docx` files and uploaded
images are lost on restart/redeploy. This is fine for a demo or testing. For real
production, add object storage (e.g. Amazon S3 via `django-storages`) so documents
persist.
