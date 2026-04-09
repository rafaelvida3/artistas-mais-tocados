<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/http.php';
require_once __DIR__ . '/src/ranking_helpers.php';
require_once __DIR__ . '/src/schema_helpers.php';
require_once __DIR__ . '/src/spotify.php';
require_once __DIR__ . '/src/renderers.php';
require_once __DIR__ . '/src/artist_bio.php';
require_once __DIR__ . '/src/shortcodes.php';
require_once __DIR__ . '/includes/schema.php';

function get_top_artists_by_genre(string $genre, int $limit = 50): array {
    return top_artists_get_top_artists_by_genre($genre, $limit);
}

function get_artist_context_by_spotify_id(string $artist_id): array {
    return top_artists_get_artist_context_by_spotify_id($artist_id);
}

function get_artist_top_tracks(string $artist_id, int $limit = 10): array {
    return top_artists_get_artist_top_tracks($artist_id, $limit);
}
