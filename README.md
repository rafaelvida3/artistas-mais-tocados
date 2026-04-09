# Top Artists Rank for WordPress

MU plugins used to power an automated music-ranking site in WordPress, with focus on SEO, caching, content generation and operational simplicity.

## Production

This project runs in production at:

https://artistasmaistocados.com.br

## What this project solves

The site needs ranking pages that stay updated without a manual editorial workflow.
Instead of publishing static lists by hand, the project pulls curated Spotify sources,
normalizes the artist data, builds ranking widgets, generates internal artist pages and
keeps the main SEO pages fresh.

This makes the project closer to a lightweight content automation system than to a
traditional WordPress customization.

## Core responsibilities

- aggregate curated Spotify playlists by genre
- rank artists by popularity and genre consistency
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

### External cron and small batches

Artist-page generation uses a seed/process flow with a lock transient and small batches.
This avoids long-running requests and fits shared-hosting constraints better than trying
to do everything in one request.

### Isolated pure helpers for tests

The most stable business rules that do not depend on WordPress runtime were extracted to
small helper files and covered with PHPUnit tests. This keeps the tests fast and cheap.

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
│       ├── config.php
│       ├── http.php
│       ├── ranking_helpers.php
│       ├── renderers.php
│       ├── schema_helpers.php
│       ├── shortcodes.php
│       └── spotify.php
├── indexnow-url-list.php
├── refresh-pages-dates.php
├── site-automation.php
└── top-artists-rank.php
```

## Why this is relevant as an engineering sample

This project demonstrates practical trade-offs instead of framework-heavy architecture:

- integration with external APIs under caching constraints
- WordPress-oriented modularization without overengineering
- SEO-aware content generation and schema customization
- operational tooling for cron, sitemap refresh and indexing workflows
- CI-based deployment for production updates

## Testing

Tests cover pure helper logic extracted from WordPress-dependent code, such as:

- ranking normalization and filtering helpers
- denylist behavior
- schema title helpers

Run locally with:

```bash
composer test
```

## Deployment

Changes pushed to `main` are validated and then deployed through GitHub Actions.
The workflow syncs the MU plugins directly to production using rsync.
