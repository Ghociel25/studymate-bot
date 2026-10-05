# StudyMate Bot — Laravel Application

Telegram-first AI assistant for students. See the full engineering documentation
(PRD, SRS, System Design, Architecture, etc.) in the project's `docs/` set —
this file only covers **running this codebase**.

## Requirements

- PHP 8.3+
- Composer 2.x
- SQLite (bundled, used for local dev) or MySQL/PostgreSQL for staging/production
- (Optional, recommended for staging/production) Redis — for queue/cache, per `TECH_STACK.md`

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # local dev uses SQLite by default
php artisan migrate
php artisan serve
```

## Verifying the App Boots

Once `php artisan serve` is running:

```bash
curl http://127.0.0.1:8000/health
```

Expected response:

```json
{"status":"ok","app":"StudyMate Bot","env":"local","time":"..."}
```

Laravel's built-in framework health check is also available at `GET /up`.

## Project Structure

Business logic lives under `app/Services/`, grouped by domain
(`Telegram`, `AI`, `Coding`, `Assignment`, `Study`, `Project`, `File`),
per `ARCHITECTURE.md`. Controllers stay thin (orchestration only);
AI provider access always goes through an interface/adapter — never
called directly from a controller. See `ARCHITECTURE.md`, `AI_DESIGN.md`,
and `CODING_STANDARDS.md` for the rules this codebase follows.

## Notes on This Baseline (TASK-001)

- Laravel version locked to `laravel/framework ^13.17` on PHP `^8.3` — recorded
  in `composer.json`, `.env.example` defaults, and `TECH_STACK.md`.
- Frontend build tooling (Vite, npm, `resources/js`, `resources/css`) was
  intentionally **not included**: the PRD marks a web dashboard as out of
  scope for MVP. This is a Telegram-only backend. If a dashboard is added
  later, frontend tooling should be reintroduced deliberately and documented.
- `database/database.sqlite` is git-ignored by design (local dev convenience);
  staging/production should use MySQL/PostgreSQL per `TECH_STACK.md`.
