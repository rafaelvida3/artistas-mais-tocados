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

1. Load a curated playlist map for each genre.
2. Fetch playlist tracks from Spotify and collect artist IDs found there.
3. Deduplicate artists while keeping an internal occurrence score per playlist pass.
4. Fetch artist metadata from Spotify.
5. Apply denylist and genre-matching heuristics.
6. Order the final ranking by Spotify popularity, with artist name as tie-breaker.

The internal score helps document how often an artist appears across curated sources, but
it is not the final ordering criterion. The final ranking is still sorted by popularity.

## Core responsibilities

- Read curated Spotify playlists by genre.
- Order final rankings by Spotify popularity after genre filtering.
- Fetch top tracks and artist metadata from Spotify.
- Enrich artist pages with biography data from Last.fm.
- Create internal artist pages automatically inside WordPress.
- Expose ranking and artist components through shortcodes.
- Inject custom JSON-LD schema for ranking and artist pages.
- Refresh modified dates of strategic SEO pages.
- Provide an admin URL export for IndexNow submission.

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
schedule is still used for lightweight recurring tasks such as weekly modified-date
refreshes.

### Isolated pure helpers for tests

The business rules that do not depend on the WordPress runtime were extracted to pure
helper files. This keeps tests and static analysis fast, cheap and predictable.

## Requirements

- PHP 8.4
- WordPress with write access to `wp-content/mu-plugins/`
- `ext-intl` enabled
- Composer for local tooling
- Spotify API credentials
- Last.fm API key for biography enrichment
- Rank Math installed in the target site for the current schema and sitemap hooks

## Required constants and runtime assumptions

Define the following constants in a safe environment-specific location such as
`wp-config.php`:

```php
define("SPOTIFY_CLIENT_ID", "your-client-id");
define("SPOTIFY_CLIENT_SECRET", "your-client-secret");
define("LASTFM_API_KEY", "your-lastfm-api-key");
define("CRON_SECRET", "a-long-random-secret");
```

What each constant is used for:

- `SPOTIFY_CLIENT_ID`: requests an app access token and powers ranking and top-track data.
- `SPOTIFY_CLIENT_SECRET`: pairs with the Spotify client ID for token requests.
- `LASTFM_API_KEY`: fetches artist biographies. The site can still rank artists without it,
  but biography enrichment becomes unavailable.
- `CRON_SECRET`: protects the public batch endpoints used to seed and process artist pages.

Operational assumptions:

- The repository is intended to be deployed under `wp-content/mu-plugins/`.
- The custom schema currently extends Rank Math through
  `wp-content/mu-plugins/top-artists-rank/includes/schema.php`.
- `top-artists-theme-integration.php` is a legacy manual JSON-LD injector. It should not be
  kept as the primary source of ranking schema when the Rank Math filter is already active,
  otherwise schema output becomes harder to reason about and compare.

## Repository structure

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
├── common/post-updates.php
├── indexnow-url-list.php
├── refresh-pages-dates.php
├── site-automation.php
├── top-artists-rank.php
└── top-artists-theme-integration.php
```

## Local setup

Install dependencies from the lock file:

```bash
composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
```

Available commands:

```bash
composer lint
composer format
composer analyse
composer test
```

For reproducible local and CI runs, prefer `composer install` over `composer update`.

## External cron flow

Artist page generation is split into two public-but-protected endpoints handled by
`wp-content/mu-plugins/site-automation.php`.

### 1. Seed the queue

Build the queue from the current rankings and reset the offset:

```text
https://your-site.example/?artist_pages_seed=1&key=YOUR_CRON_SECRET
```

Expected responses:

- `Seed OK`
- `Forbidden`

### 2. Process the queue in batches

Create or refresh artist pages in batches of 10:

```text
https://your-site.example/?artist_pages_process=1&key=YOUR_CRON_SECRET
```

Expected responses:

- `Processed {offset}`
- `Done`
- `Locked`
- `Empty`
- `Parent page error`
- `Forbidden`

Recommended execution model:

1. Call the seed endpoint once before a full run.
2. Call the process endpoint repeatedly from an external scheduler until it returns `Done`.
3. Keep the scheduler outside WordPress. The plugin already uses a transient lock to avoid
   overlapping batches.

## Cache behavior

The project intentionally uses only WordPress transients for remote data caching.

Current cache behavior in code:

- Spotify app token: cached with the token TTL minus a small safety margin.
- Spotify artist metadata: 12 hours.
- Spotify top tracks: 12 hours.
- Last.fm biography success: 30 days.
- Last.fm biography empty result: 12 hours.
- Artist page batch lock: 120 seconds.

This keeps the runtime simple and avoids custom tables, queues or workers.

## Schema behavior

The schema stack has two distinct layers:

1. Rank Math base graph, which already outputs site-level schema such as `Organization`,
   `WebSite` and `WebPage`.
2. Project-specific enrichment through `top_artists_filter_schema_graph()`, which adds the
   ranking `ItemList` payloads, artist schema and controlled breadcrumb replacement.

The current tests cover this integration path directly. That is the schema entry point that
should be treated as the source of truth for ranking pages and artist pages.

## Testing

Tests currently cover:

- Genre normalization, denylist checks and genre heuristics.
- Artist candidate aggregation and internal score/frequency tracking.
- Ranking row ordering logic.
- Schema title and `ItemList` builders.
- Batch and lock window helpers for artist-page automation.
- A smoke test for the Rank Math schema filter integration on the home page.

Run locally with:

```bash
composer analyse
composer test
```

## Deployment

Changes pushed to `main` are validated and then deployed through GitHub Actions.
The workflow runs code style checks, static analysis and tests before syncing the MU
plugins to production through rsync.

At deployment time, make sure the target environment already contains:

- the required constants in `wp-config.php`
- Rank Math installed and configured
- the destination directory `wp-content/mu-plugins/`
- any environment-specific files intentionally excluded from rsync

## Notes for maintainers

- Ranking pages are defined centrally in `top_artists_get_ranking_pages_map()`.
- Supported genres and curated playlist IDs are defined in `project_config.php`.
- The homepage and ranking pages are part of the modified-date refresh flow.
- Artist pages are created under the `artista` parent page and keyed by Spotify artist ID.
- Sitemap cache invalidation currently depends on Rank Math being available.
