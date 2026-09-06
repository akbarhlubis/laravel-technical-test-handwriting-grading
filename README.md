# TingXie HERO Laravel

Laravel reimplementation of the TingXie HERO handwriting grading application.
The original Next.js application is the behavioral reference:
[technical-test-handrwiting-grading-app](https://github.com/akbarhlubis/technical-test-handrwiting-grading-app).

This repository is not a line-by-line port. It reproduces the domain behavior
using Laravel routing, Eloquent, Inertia, React, and server-side integrations.
Development is intentionally backend-first.

## Status

**Backend Development in Progress**

Current milestone: Supabase PostgreSQL, private Supabase Storage, backend
submission persistence, and the Gemini observation boundary are implemented and
automated-tested. Deterministic result normalization is now implemented and
automated-tested. Live Gemini verification reached the provider but is currently
blocked by a provider HTTP 503 response.

## Architecture

```text
React + Inertia
       |
       v
    Laravel
       |
       +--> Supabase PostgreSQL
       |
       +--> Supabase Storage
```

Laravel owns routing and backend orchestration. Inertia allows the React UI to
use Laravel routes without introducing a separate SPA API and CORS layer.

### Database

Laravel connects directly to the existing Supabase PostgreSQL database through
PDO, Eloquent, and Laravel's PostgreSQL connection. Lessons use the existing
`public` schema; this project does not own or migrate that schema.

### Storage

Handwriting images are uploaded through Laravel's HTTP client and the official
Supabase Storage HTTP API. Privileged credentials remain server-side. Images
are stored in the private `handwriting-submissions` bucket, and application
data stores an object path rather than a permanent public URL.

### Backend-first development

Storage, persistence, AI grading, normalization, scoring, and failure behavior
are being implemented and tested independently before rebuilding the complete
frontend.

## Current Implementation

### Foundation

- Laravel 13
- Inertia.js
- React 19
- Tailwind CSS v4
- Laravel to Inertia rendering

### Database

- Dedicated Supabase PostgreSQL connection
- Existing `public` schema integration
- UUID-based `Lesson` model
- Native PostgreSQL `text[]` `word_list` normalization
- `GET /lessons`
- Laravel to Supabase to Inertia lesson flow
- Real PostgreSQL connectivity verified

### Storage

- Private `handwriting-submissions` bucket
- Server-side Laravel Storage REST integration
- Laravel HTTP client
- Server-only `SUPABASE_SECRET_KEY`
- JPEG, JPG, PNG, and WebP validation
- 5 MB upload limit
- UUID-based immutable object paths
- `POST /submissions/upload` proof endpoint
- Automated Storage tests
- Real private Storage upload verified
- Unauthenticated public access verified to fail

## Database Schema

The following existing Supabase tables support the planned grading workflow:

```text
lessons
  |
  +-- submissions
        |
        +-- character_results
```

Conceptual fields:

`lessons`

- `id` UUID
- `title`
- `moe_level`
- `word_list` PostgreSQL `text[]`
- `created_at`

`submissions`

- `id` UUID
- `lesson_id`
- `student_id` nullable
- `image_path`
- `score` nullable
- `created_at`

`character_results`

- `id` UUID
- `submission_id`
- `character_name`
- `recognized_text` nullable
- `is_correct`
- `created_at`

These are existing Supabase tables. Do not run Laravel migrations against this
schema as part of local development.

## Routes

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/` | Inertia and React foundation page |
| `GET` | `/lessons` | Read lessons through Eloquent and Inertia |
| `POST` | `/submissions` | Upload an image and persist a pending submission |

## Technology

### Backend

- PHP
- Laravel 13
- Eloquent
- Laravel HTTP Client

### Frontend

- React 19
- Inertia.js
- Tailwind CSS v4
- Vite

### Infrastructure and services

- Supabase PostgreSQL
- Supabase Storage
- Gemini observation API: implemented; live verification pending
- Nginx and PHP-FPM VPS deployment: planned

## Environment

Copy `.env.example` to `.env`, then configure the local values:

```dotenv
SUPABASE_DB_URL=
SUPABASE_URL=
SUPABASE_SECRET_KEY=
SUPABASE_STORAGE_BUCKET=handwriting-submissions
```

Use the Session Pooler PostgreSQL URL where appropriate for local application
traffic. Never commit real Supabase credentials, print them in logs, or expose
them through Inertia or frontend bundles.

## Local Development

```bash
composer install
npm install
```

Create the local environment file, configure the Supabase values above, then
generate the application key and clear cached configuration:

```bash
php artisan key:generate
php artisan config:clear
```

Run the existing development commands as needed:

```bash
composer run dev
npm run dev
```

Do not run migrations against the existing Supabase schema.

## Testing

```bash
php artisan test
vendor/bin/pint --test
npm run build
git diff --check
```

The current tests cover the lesson flow, Storage validation, Storage HTTP
requests, safe upload failures, and secret non-disclosure. Automated tests do
not require live Supabase Storage access.

## Progress

### Foundation

- [x] Laravel
- [x] Inertia + React
- [x] Tailwind
- [x] Inertia rendering

### Database

- [x] Supabase PostgreSQL
- [x] Lesson model
- [x] PostgreSQL `text[]` normalization
- [x] `GET /lessons`
- [x] Real database connection verification

### Storage

- [x] Private bucket
- [x] Laravel Storage service
- [x] Image validation
- [x] UUID object paths
- [x] Automated tests
- [x] Real upload verification

### Backend grading

- [x] Submission persistence
- [x] Gemini observation integration
- [x] Structured output validation
- [x] Deterministic result normalization
- [x] Deterministic score calculation in preview
- [ ] `character_results` persistence
- [ ] Gemini temporary failure handling
- [ ] Complete grading endpoint

### Frontend

- [ ] Dashboard
- [ ] Syllabus P1-P6
- [ ] Camera
- [ ] Submission UI
- [ ] Score result
- [ ] Correction annotations
- [ ] History

### Deployment

- [ ] VPS deployment
- [ ] Nginx
- [ ] PHP-FPM
- [ ] Production configuration

## Not Yet Implemented

- Submission database persistence
- Gemini normalization and grading
- Grading normalization
- Persisted grading score
- `character_results` persistence
- Gemini 503 retry and resilience behavior
- Complete camera UI
- Grading results UI
- Correction annotations
- Results history
- Final dashboard and syllabus UI
- VPS production deployment
