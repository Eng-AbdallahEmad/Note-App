<div align="center">
  <img src="public/assets/note_logo.png" alt="Notes App Logo" width="120" />

  <h1>Notes API</h1>

  <p>A clean, fast, and well-structured RESTful API built with Laravel 12 — following Clean Code principles with layered architecture, smart caching, and rate limiting out of the box.</p>

  ![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel&logoColor=white)
  ![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)
  ![License](https://img.shields.io/badge/License-MIT-green?style=flat)
</div>

---

## Table of Contents

- [Overview](#overview)
- [Clean Code Architecture](#clean-code-architecture)
- [Project Structure](#project-structure)
- [Request Flow](#request-flow)
- [API Endpoints](#api-endpoints)
- [Caching Strategy](#caching-strategy)
- [Rate Limiting](#rate-limiting)
- [Database Schema](#database-schema)
- [Getting Started](#getting-started)

---

## Overview

Notes API is a RESTful backend service for managing personal notes. Each note supports a title, content, and a pin feature that prioritizes important notes at the top. The project is intentionally minimal but architecturally solid — built to demonstrate real-world Laravel patterns used in production codebases.

**Key highlights:**
- Clean layered architecture: Controller → Service → Repository
- Smart cache invalidation using a version-based key strategy
- API versioning under `/api/v1`
- Rate limiting via Laravel's built-in throttle middleware
- Consistent JSON responses via API Resources
- Strict input validation via Form Requests

---

## Clean Code Architecture

The project is structured around the **separation of concerns** principle. Each layer has a single, clear responsibility.

```
┌─────────────────────────────────────────────────────────┐
│                      HTTP Layer                         │
│   FormRequest (validation)  →  Controller (dispatch)   │
└─────────────────────┬───────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────┐
│                   Service Layer                         │
│         NoteService  ←  Business Logic + Cache          │
└─────────────────────┬───────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────┐
│                 Repository Layer                        │
│         NoteRepository  ←  Database Queries Only        │
└─────────────────────┬───────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────┐
│                   Data Layer                            │
│              Note Model  ←  Eloquent ORM                │
└─────────────────────────────────────────────────────────┘
```

### Why this architecture?

| Layer | Responsibility | Benefit |
|---|---|---|
| **Controller** | Receives request, delegates, returns response | Thin — no business logic leaks in |
| **FormRequest** | Validates and authorizes incoming data | Validation never lives inside controllers |
| **Service** | Business logic, caching, orchestration | The only layer allowed to make decisions |
| **Repository** | Raw database access | Swappable — change DB without touching logic |
| **Resource** | Transforms models to JSON | API shape is defined in one place |

### Single Responsibility in practice

```php
// Controller — only dispatches, never queries
public function index(): JsonResponse
{
    $notes = $this->noteService->getAll();
    return response()->json(NoteResource::collection($notes));
}

// Service — owns logic and cache
public function getAll(): LengthAwarePaginator
{
    $page    = (int) request()->get('page', 1);
    $version = (int) Cache::get('notes.list.version', 0);

    return Cache::remember("notes.list.v{$version}.page.{$page}", self::LIST_TTL, fn() =>
        $this->repo->getAll()
    );
}

// Repository — only talks to the database
public function getAll(): LengthAwarePaginator
{
    return Note::orderByDesc('is_pinned')
               ->orderByDesc('created_at')
               ->paginate(15);
}
```

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/
│   │       └── NoteController.php      # Thin controller, delegates to service
│   ├── Requests/
│   │   ├── StoreNoteRequest.php        # Validation for create
│   │   └── UpdateNoteRequest.php       # Validation for update
│   └── Resources/
│       └── NoteResource.php            # Consistent JSON output shape
├── Models/
│   └── Note.php                        # Eloquent model with casts
├── Repositories/
│   └── NoteRepository.php              # Database queries only
└── Services/
    └── NoteService.php                 # Business logic + caching

routes/
└── api.php                             # Versioned API routes with throttle

resources/views/
└── welcome.blade.php                   # Interactive frontend (Vanilla JS + Bootstrap RTL)
```

---

## Request Flow

Every API request goes through a consistent, predictable flow:

```
Client Request
      │
      ▼
┌─────────────┐
│  Throttle   │  100 requests / minute — returns 429 if exceeded
│ Middleware  │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   Router    │  Matches /api/v1/notes/*
│  api.php    │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ FormRequest │  Validates input — returns 422 if invalid
│ (if needed) │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ Controller  │  Delegates to NoteService
└──────┬──────┘
       │
       ▼
┌─────────────┐       Cache HIT?
│   Service   │ ─────────────────► Return cached data instantly
│ NoteService │
└──────┬──────┘  Cache MISS ↓
       │
       ▼
┌─────────────┐
│ Repository  │  Queries the database
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Resource   │  Shapes the model into consistent JSON
│ NoteResource│
└──────┬──────┘
       │
       ▼
  JSON Response
```

### Write operation flow (create / update / delete)

```
Client  →  Throttle  →  FormRequest  →  Controller
                                              │
                                         NoteService
                                         ┌────┴────────┐
                                    Repository      Bust Cache
                                         │         (put version + 1)
                                      Database
                                         │
                                    NoteResource
                                         │
                                   JSON Response
```

---

## API Endpoints

**Base URL:** `/api/v1`

| Method | Endpoint | Description | Cache |
|---|---|---|---|
| `GET` | `/notes` | List all notes (paginated) | Cached 5 min |
| `POST` | `/notes` | Create a new note | Busts list cache |
| `GET` | `/notes/{id}` | Get a single note | Cached 1 hour |
| `PUT` | `/notes/{id}` | Update a note | Busts list + note cache |
| `DELETE` | `/notes/{id}` | Delete a note | Busts list + note cache |

### Request & Response Examples

**Create a note** — `POST /api/v1/notes`

```json
// Request body
{
    "title": "My first note",
    "content": "Something worth remembering.",
    "is_pinned": true
}

// Response 201
{
    "id": 1,
    "title": "My first note",
    "content": "Something worth remembering.",
    "is_pinned": true,
    "created_at": "2026-05-29T10:00:00.000000Z",
    "updated_at": "2026-05-29T10:00:00.000000Z"
}
```

**List notes** — `GET /api/v1/notes`

```json
{
    "data": [
        {
            "id": 1,
            "title": "My first note",
            "content": "Something worth remembering.",
            "is_pinned": true,
            "created_at": "2026-05-29T10:00:00.000000Z",
            "updated_at": "2026-05-29T10:00:00.000000Z"
        }
    ],
    "links": { "...": "..." },
    "meta": { "current_page": 1, "total": 1 }
}
```

**Validation error** — `422 Unprocessable Entity`

```json
{
    "message": "The title field is required.",
    "errors": {
        "title": ["The title field is required."]
    }
}
```

**Rate limit exceeded** — `429 Too Many Requests`

```json
{
    "message": "Too Many Attempts."
}
```

---

## Caching Strategy

Caching lives entirely in the **Service layer** — the controller and repository know nothing about it.

### How list cache invalidation works

Instead of tracking and deleting individual page cache keys, the project uses a **version-based key strategy**:

```
Cache key = "notes.list.v{version}.page.{page}"

On first load:    notes.list.v0.page.1  ✓ cached
On second page:   notes.list.v0.page.2  ✓ cached

After create / update / delete:
  → notes.list.version bumped to 1
  → All v0 keys become unreachable (expire naturally after 5 min)
  → notes.list.v1.page.1  ← fresh cache built on next request
```

This busts all paginated list caches in a single operation — no loops, no key tracking, no risk of stale pages.

### Why `Cache::put` instead of `Cache::increment`

`Cache::increment` on a key that doesn't exist yet sets it to **1**. If the default used in `Cache::get` is also **1**, the version never effectively changes on the first mutation — the list cache stays stale.

The fix uses an explicit `put` to guarantee the version always moves forward:

```php
// Unsafe — first increment sets key to 1, same as the old default
Cache::increment('notes.list.version');

// Safe — always reads current value and adds 1 explicitly
Cache::put('notes.list.version', (int) Cache::get('notes.list.version', 0) + 1);
```

The version starts at **0** (default), so the first mutation bumps it to **1**, producing a new cache key that is guaranteed to be a miss.

### Cache TTLs

| What | Key Pattern | TTL |
|---|---|---|
| Notes list (per page) | `notes.list.v{n}.page.{n}` | 5 minutes |
| Single note | `notes.{id}` | 1 hour |
| List version counter | `notes.list.version` | Permanent |

### Cache driver

Configured via `CACHE_STORE` in `.env`. Defaults to `database` — no Redis required.

```env
# .env — switch to Redis when needed
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## Rate Limiting

All API routes are wrapped in a throttle middleware defined in `routes/api.php`:

```php
Route::middleware('throttle:100,1')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::apiResource('notes', NoteController::class);
    });
});
```

- **100 requests per minute** per IP
- Returns `429 Too Many Requests` when exceeded
- Resets automatically after the 1-minute window

---

## Database Schema

```
notes
┌─────────────┬──────────────┬──────────────────────┐
│   Column    │     Type     │        Notes         │
├─────────────┼──────────────┼──────────────────────┤
│ id          │ BIGINT (PK)  │ Auto increment        │
│ title       │ VARCHAR(255) │ Required              │
│ content     │ TEXT         │ Required              │
│ is_pinned   │ BOOLEAN      │ Default: false        │
│ created_at  │ TIMESTAMP    │ Auto-managed          │
│ updated_at  │ TIMESTAMP    │ Auto-managed          │
└─────────────┴──────────────┴──────────────────────┘
```

Notes are ordered by `is_pinned DESC, created_at DESC` — pinned notes always appear first.

---

## Getting Started

### Requirements

- PHP 8.2+
- Composer
- MySQL
- Laravel 12

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/your-username/notesapi.git
cd notesapi

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Set your database credentials in .env
DB_DATABASE=notesapi
DB_USERNAME=root
DB_PASSWORD=

# 5. Run migrations (creates notes + cache tables)
php artisan migrate

# 6. Serve the application
php artisan serve
```

Visit `http://localhost:8000` to open the interactive UI.

---

<div align="center">
  Built with Laravel 12
</div>
