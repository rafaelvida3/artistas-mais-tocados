# Top Artists Rank for WordPress

MU plugins used to power an automated music-ranking site in WordPress, with focus on SEO, performance and programmatic content generation.

This project aggregates curated Spotify playlists by genre, ranks artists by popularity, creates internal artist pages, enriches content with external data, and injects structured data for better search visibility.

## What this project does

- Builds artist rankings by genre using curated Spotify playlists
- Fetches artist metadata, top tracks and popularity from the Spotify API
- Enriches artist pages with bio data from Last.fm
- Creates artist pages automatically inside WordPress
- Exposes ranking widgets and artist blocks through shortcodes
- Adds custom JSON-LD schema for ranking pages and artist pages
- Refreshes page modification dates for important SEO pages
- Generates a plain-text admin list of URLs for IndexNow submission
- Temporarily bypasses AIOS in cron and sitemap requests to avoid operational issues

## Why this project is relevant

This is not a generic WordPress customization. It is a small content automation system built inside WordPress, combining:

- external API integration
- caching strategy with transients
- SEO-oriented page generation
- structured data customization
- operational tooling for sitemap and indexing workflows

## Repository structure

```text
wp-content/mu-plugins/
├── common/
│   └── post-updates.php
├── top-artists-rank/
│   ├── bootstrap.php
│   └── includes/
│       └── schema.php
├── disable-aios.php
├── indexnow-url-list.php
├── refresh-pages-dates.php
├── site-automation.php
└── top-artists-rank.php