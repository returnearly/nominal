# Fuzzy Monitor Search Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox syntax.

**Goal:** Case-insensitive substring monitor search with light typo tolerance, plus case-insensitive searchable tag filter.

**Architecture:** Shared `App\Support\FuzzySearch` helper applies tokenized, escaped, driver-aware LIKE patterns. Wired into `MonitorResource` table `searchUsing` and `Monitor::scopeTagged`.

**Tech Stack:** Laravel 13, Filament 5, Pest, SQLite (default)

## Global Constraints

- No new Composer/npm dependencies
- Default branch is `master`; do not create `main`
- Prefer existing `App\Support` patterns (`final` classes, strict types)

---

### Task 1: FuzzySearch helper (TDD)

**Files:**
- Create: `app/Support/FuzzySearch.php`
- Create: `tests/Unit/FuzzySearchTest.php`

- [ ] Write unit tests for tokenize, escape, patterns (≥4 char typo `_` variants), and `apply()` matching on Monitor query
- [ ] Run tests — expect fail
- [ ] Implement `FuzzySearch`
- [ ] Run tests — expect pass

### Task 2: Wire table search + tag filter

**Files:**
- Modify: `app/Filament/Resources/Monitors/MonitorResource.php`
- Modify: `app/Filament/Tables/Columns/MonitorCardColumn.php`
- Modify: `app/Models/Monitor.php`
- Modify: `tests/Feature/Filament/MonitorResourceTest.php`

- [ ] Add feature tests: cross-page search, case-insensitive search, case-insensitive tag filter
- [ ] Run — expect fail
- [ ] Move search to table `searchUsing` via `FuzzySearch`; strip column search; make tag filter searchable; CI `scopeTagged`
- [ ] Run feature + unit tests — expect pass
