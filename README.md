# Top Artists Rank for WordPress

MU plugins used to power an automated music-ranking site in WordPress, with focus on
SEO, caching, content generation and operational simplicity.

## Production

This project runs in production at:

https://artistasmaistocados.com.br

## What this project solves

The site needs ranking pages that stay updated without a manual editorial workflow.
Instead of publishing static lists by hand, the project reads curated Spotify playlists,
collects the artists found in those playlists, fetches artist details, filters the result
by genre rules and renders ranking pages and artist pages inside WordPress.

This makes the project closer to a lightweight content automation system than to a
traditional WordPress customization.

## How the ranking works today

The current ranking flow is intentionally simple and matches the code in this repository:

1. load a curated playlist map for each genre
2. fetch playlist tracks from Spotify and collect artist IDs found there
3. deduplicate artists while keeping an internal occurrence score per playlist pass
4. fetch artist metadata from Spotify
5. apply denylist and genre-matching heuristics
6. order the final ranking by Spotify popularity, with artist name as tie-breaker

The internal score helps document how often an artist appears across curated sources, but
it is not the final ordering criterion. The final ranking is still sorted by popularity.

## Core responsibilities

- read curated Spotify playlists by genre
- order final rankings by Spotify popularity after genre filtering
- fetch top tracks and artist metadata from Spotify
- enrich artist pages with biography data from Last.fm
- create internal artist pages automatically inside WordPress
- expose ranking and artist components through shortcodes
- inject custom JSON-LD schema for ranking and artist pages
- refresh modified dates of strategic SEO pages
- provide an admin URL export for IndexNow submission

## Main architectural decisions

### MU plugins instead of a standard plugin

The project lives in `mu-plugins` because the ranking, schema and automation logic are
critical to the site and should load consistently without manual activation.

### Curated sources instead of broad search

The rankings are built from a curated playlist map per genre. This keeps the behavior
predictable, makes the output easier to review and reduces noise from weak matches.

### Transients as the main cache layer

External API responses are cached with WordPress transients. This keeps the
implementation simple, avoids unnecessary infrastructure and reduces repeated calls to
Spotify and Last.fm.

### External cron with lightweight WP-Cron support

The project primarily relies on external cron for execution. A small internal WP-Cron
schedule is still used for lightweight recurring tasks:

```php
// WP-Cron is used only for lightweight internal scheduling.
// External cron remains the primary execution trigger.
```

### Isolated pure helpers for tests

The business rules that do not depend on the WordPress runtime were extracted to pure
helper files. This keeps tests and static analysis fast, cheap and predictable.

## Current repository structure

```text
wp-content/mu-plugins/
├── common/
│   └── post-updates.php
├── top-artists-rank/
│   ├── bootstrap.php
│   ├── includes/
│   │   └── schema.php
│   └── src/
│       ├── artist_bio.php
│       ├── automation_helpers.php
│       ├── config.php
│       ├── http.php
│       ├── project_config.php
│       ├── ranking_engine.php
│       ├── ranking_helpers.php
│       ├── renderers.php
│       ├── schema_builders.php
│       ├── schema_helpers.php
│       ├── shortcodes.php
│       └── spotify.php
├── indexnow-url-list.php
├── refresh-pages-dates.php
├── site-automation.php
└── top-artists-rank.php
```

## Tooling

The repository includes:

- `composer lint` for PHP CS Fixer checks
- `composer analyse` for PHPStan static analysis on pure PHP helpers
- `composer test` for PHPUnit coverage of ranking, schema and batch helpers

The project also requires `ext-intl` because genre normalization uses
`transliterator_transliterate()` for accent-insensitive comparisons.

## Testing

Tests cover:

- genre normalization, denylist checks and genre heuristics
- artist candidate aggregation and internal score/frequency tracking
- ranking row ordering logic
- schema title and ItemList builders
- batch and lock window helpers for artist-page automation

Run locally with:

```bash
composer analyse
composer test
```

## Deployment

Changes pushed to `main` are validated and then deployed through GitHub Actions.
The workflow runs lint, static analysis and tests before syncing the MU plugins to
production through rsync.
