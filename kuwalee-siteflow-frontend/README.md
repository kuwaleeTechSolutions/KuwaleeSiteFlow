# Kuwalee SiteFlow Frontend (v2)

Production-oriented React + TypeScript + Vite frontend for the Kuwalee
SiteFlow Laravel API, rebuilt to correctly match the real backend contract
(nested resources, workflow actions, PDF export, document sharing) after
issues were found in the first delivery.

## What changed since v1

- **Sites, BOQ, Bills are now dedicated pages** matching their real nested
  backend routes (`/projects/{id}/sites`, `/projects/{id}/boq-items`,
  `/projects/{id}/bills`) instead of assuming flat endpoints that never
  existed — this was the exact cause of the "resource not found" error.
- **Payments** are managed inside a modal launched from a Bill row (nested
  under `/bills/{id}/payments`), including recording new payments.
- **Users & Roles management screens** — did not exist in v1 at all. Users
  page supports invite + role reassignment; Roles page supports viewing
  system role templates and creating custom roles with a full permission
  checklist mirrored from the backend's permission catalogue.
- **Daily Reports** now has a real approval workflow (submit / approve /
  return-with-remarks) and a working secure photo upload + download modal.
- **Measurements** now has submit / approve / reject (with required
  remarks) and a working PDF export button.
- **Bills** now has submit / certify actions and a working PDF export
  button.
- **Documents** now has a working download action (secure blob download,
  not a raw link).
- **Eye (view) and delete buttons** on every generic list are now wired to
  real actions — v1 had these as inert placeholder buttons.
- **Form validation errors are now visible** — v1 silently swallowed 422
  responses, which is why the Create Project form appeared to "do nothing."
- **Super Admin now has a working screen** (`/system/organizations`) to
  create organisations — v1 logged Super Admin in with no usable screen at
  all.

## Run locally

```bash
cp .env.example .env
npm install
npm run dev
```

Open the printed local URL and either sign in against a real running
backend, or click **Open demo workspace** to explore every screen with
built-in sample data and no backend required.

## Connect to the real backend

Make sure `backend/bootstrap/providers.php` exists (see `BACKEND_AUDIT.md`
in the project root — this was a missing file that silently disabled
Policies and rate limiters) and the backend is running:

```bash
cd ../backend
php artisan serve --host=127.0.0.1 --port=8000
```

Then, with `VITE_ENABLE_DEMO=false` in `.env`, sign in with a real account.

## Build for production

```bash
npm run build
npm run preview
```

Deploy the generated `dist/` folder behind Nginx (see
`nginx.conf.example`) or any static host, routing all non-file paths to
`index.html` for React Router, and proxying `/api` and `/sanctum` to the
Laravel backend.

## Known intentional limitations

- **Bill items require a Measurement Item ID typed manually.** There is no
  backend endpoint to browse a project's approved-but-unbilled measurement
  items as a flat list, so this is currently a manual step: open the
  Measurements page, use "View details" on an approved measurement to find
  its item ID, then enter it on the Bill creation form.
- **Document sharing (choosing specific users to share a restricted
  document with) is not yet wired to a UI** — the backend endpoint
  (`POST /documents/{id}/share`) exists and works; only the picker UI is
  still pending. This is the one remaining gap called out honestly rather
  than silently skipped.
