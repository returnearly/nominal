# Fuzzy case-insensitive monitor search

## Goal

Monitors list search and the tag filter should match case-insensitively with substring semantics, plus light single-character typo tolerance, without new dependencies.

## Scope

- Monitors Filament table search (name, target, description, tags JSON text)
- Tag `SelectFilter` (searchable dropdown + case-insensitive tag match via `scopeTagged`)
- Out of scope: other Filament resources, GraphQL text search, full-text engines

## Matching rules

1. Trim, collapse whitespace; empty query = no filter.
2. Split on spaces into tokens; **all** tokens must match (`AND`).
3. Each token may match **any** searchable column (`OR`).
4. Baseline match: case-insensitive substring (`LOWER(col) LIKE %token%`, driver-aware).
5. Escape user `%` / `_` so they are literal.
6. Typo looseness: for tokens with length ≥ 4, also OR single-character substitution patterns using `_` (one wrong character). Shorter tokens stay exact-substring only.
7. Table-level `searchUsing` (not column search) so results span all pages.

## Tag filter

- Keep `SelectFilter` options from existing tags; add `->searchable()` for substring option picking.
- `Monitor::scopeTagged` matches case-insensitively (exact tag value ignoring case), preserving GraphQL `@whereJsonContains` callers that pass the stored casing via exact path remaining valid when casing matches.

## Implementation shape

- `App\Support\FuzzySearch`: tokenize, build LIKE patterns (exact + typo), apply to a query + column list.
- `MonitorResource::table()`: `searchable()` + `searchUsing` calling the helper; remove column-level search from `MonitorCardColumn`.
- Update `scopeTagged` for case-insensitive JSON tag match across sqlite/mysql/pgsql.

## Tests

- Unit: tokenization, escaping, typo pattern generation, query matching (CI + multi-token + typo).
- Feature: Filament search across pages; case-insensitive name search; case-insensitive tag filter.
