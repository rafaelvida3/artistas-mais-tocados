<?php

declare(strict_types=1);

function top_artists_sanitize_artist_bio(string $bio): string {
    $bio = html_entity_decode($bio, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $bio = wp_strip_all_tags($bio);
    $bio = preg_replace('/Read more on Last\.fm.*$/i', '', $bio);
    $bio = preg_replace('/User-contributed text.*$/i', '', $bio);
    $bio = preg_replace('/[ \t]+/', ' ', (string) $bio);
    $bio = preg_replace("/\r\n|\r/", "\n", (string) $bio);
    $bio = preg_replace("/(?<!\n)\n(?!\n)/", "\n\n", (string) $bio);
    $bio = preg_replace("/\n{3,}/", "\n\n", (string) $bio);

    return trim((string) $bio);
}

function top_artists_get_artist_bio_cache_key(string $artist_name): string {
    return 'artist_bio_' . md5(strtolower(trim($artist_name)));
}

function top_artists_get_default_artist_bio_text(): string {
    return 'Confira as músicas mais tocadas e os maiores sucessos deste artista.';
}

function top_artists_get_artist_bio_if_available(array $artist): string {
    $artist_name = isset($artist['artist_name']) ? trim((string) $artist['artist_name']) : '';

    if ($artist_name === '') {
        $artist_name = isset($artist['name']) ? trim((string) $artist['name']) : '';
    }

    if ($artist_name === '') {
        return '';
    }

    return top_artists_get_artist_bio_by_name($artist_name);
}

function top_artists_request_artist_bio_from_lastfm(
    string $artist_name,
    string $lang = 'pt',
): string {
    if ($artist_name === '') {
        return '';
    }

    if (! defined('LASTFM_API_KEY') || (string) LASTFM_API_KEY === '') {
        error_log('[LastFM] API key not defined');

        return '';
    }

    $query_args = [
        'method' => 'artist.getinfo',
        'artist' => $artist_name,
        'api_key' => (string) LASTFM_API_KEY,
        'format' => 'json',
    ];

    if ($lang !== '') {
        $query_args['lang'] = $lang;
    }

    $response = wp_remote_get(
        add_query_arg($query_args, 'https://ws.audioscrobbler.com/2.0/'),
        [
            'timeout' => TOP_ARTISTS_REQUEST_TIMEOUT,
        ],
    );

    if (is_wp_error($response)) {
        error_log('[LastFM] WP_Error: ' . $response->get_error_message());

        return '';
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
        error_log('[LastFM] HTTP error: ' . $status_code . ' | Artist: ' . $artist_name);

        return '';
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);

    if (! is_array($json)) {
        error_log('[LastFM] Invalid JSON response | Artist: ' . $artist_name);

        return '';
    }

    if (! isset($json['artist'])) {
        error_log('[LastFM] Missing artist key in response | Artist: ' . $artist_name);

        return '';
    }

    $bio_content = $json['artist']['bio']['content'] ?? '';
    $bio_summary = $json['artist']['bio']['summary'] ?? '';

    if (is_string($bio_content) && $bio_content !== '') {
        return top_artists_sanitize_artist_bio($bio_content);
    }

    if (is_string($bio_summary) && $bio_summary !== '') {
        return top_artists_sanitize_artist_bio($bio_summary);
    }

    return '';
}

function top_artists_get_artist_bio_by_name(string $artist_name): string {
    if ($artist_name === '') {
        return '';
    }

    $cache_key = top_artists_get_artist_bio_cache_key($artist_name);
    $cached_bio = get_transient($cache_key);

    if (is_string($cached_bio)) {
        return $cached_bio;
    }

    $bio = top_artists_request_artist_bio_from_lastfm($artist_name, 'pt');

    if ($bio === '') {
        $bio = top_artists_request_artist_bio_from_lastfm($artist_name, '');
    }

    if ($bio === '') {
        set_transient($cache_key, '', 12 * HOUR_IN_SECONDS);

        return '';
    }

    set_transient($cache_key, $bio, 30 * DAY_IN_SECONDS);

    return $bio;
}
