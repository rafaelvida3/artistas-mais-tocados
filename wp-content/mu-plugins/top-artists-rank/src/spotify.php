<?php

declare(strict_types=1);

use WpOrg\Requests\Requests;

/**
 * @return array<string>
 */
function top_artists_get_playlists_for_genre(string $genre): array {
    $playlists_by_genre = top_artists_get_curated_playlists_map();
    $playlists = $playlists_by_genre[$genre] ?? [];

    return is_array($playlists) ? $playlists : [];
}

/**
 * @param array<string> $artist_ids
 * @return array<string, ?array>
 */
function top_artists_fetch_artist_top_tracks_parallel(string $token, array $artist_ids): array {
    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
    $requests = [];
    $prefilled = [];

    foreach (array_unique(array_filter(array_map('strval', $artist_ids))) as $artist_id) {
        $cache_key = 'artist_top_tracks_' . $artist_id;
        $cached_tracks = get_transient($cache_key);

        if (is_array($cached_tracks)) {
            $prefilled[$artist_id] = $cached_tracks;
            continue;
        }

        $requests[$artist_id] = [
            'url' => TOP_ARTISTS_API_BASE_URL
                . '/artists/'
                . $artist_id
                . '/top-tracks?market='
                . TOP_ARTISTS_MARKET,
            'headers' => $headers,
            'type' => Requests::GET,
            'options' => [
                'timeout' => TOP_ARTISTS_REQUEST_TIMEOUT,
            ],
        ];
    }

    if ($requests === []) {
        return $prefilled;
    }

    $fetched = top_artists_request_multiple_json(
        $requests,
        static function (string $artist_id, array $json): ?array {
            $tracks = isset($json['tracks']) && is_array($json['tracks'])
                ? $json['tracks']
                : null;

            if (is_array($tracks)) {
                set_transient('artist_top_tracks_' . $artist_id, $tracks, 12 * HOUR_IN_SECONDS);
            }

            return $tracks;
        },
        static fn (string $artist_id): ?array => null,
    );

    return $prefilled + $fetched;
}

/**
 * @param array<string> $playlist_ids
 * @return array<string, array>
 */
function top_artists_fetch_playlist_tracks_parallel(
    string $token,
    array $playlist_ids,
    int $limit = 100,
): array {
    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
    $requests = [];
    $prefilled = [];

    foreach ($playlist_ids as $playlist_id) {
        $cache_key = 'playlist_tracks_' . $playlist_id . '_' . $limit;
        $cached_tracks = get_transient($cache_key);

        if (is_array($cached_tracks)) {
            $prefilled[$playlist_id] = $cached_tracks;
            continue;
        }

        $requests[$playlist_id] = [
            'url' => add_query_arg(
                [
                    'limit' => $limit,
                    'fields' => (
                        'items(added_at,track(artists(id,name),id,name,'
                        . 'album(release_date,release_date_precision))),next'
                    ),
                    'market' => TOP_ARTISTS_MARKET,
                ],
                TOP_ARTISTS_API_BASE_URL . '/playlists/' . $playlist_id . '/tracks',
            ),
            'headers' => $headers,
            'type' => Requests::GET,
            'options' => [
                'timeout' => TOP_ARTISTS_REQUEST_TIMEOUT,
            ],
        ];
    }

    if ($requests === []) {
        return $prefilled;
    }

    $fetched = top_artists_request_multiple_json(
        $requests,
        static function (string $playlist_id, array $json) use ($limit): array {
            $items = isset($json['items']) && is_array($json['items'])
                ? $json['items']
                : [];
            $tracks = [];

            foreach ($items as $item) {
                if (! is_array($item) || empty($item['track']) || ! is_array($item['track'])) {
                    continue;
                }

                $tracks[] = $item['track'];
            }

            if ($tracks === []) {
                error_log(sprintf('[top-artists] PLAYLIST EMPTY :: %s', $playlist_id));
            }

            set_transient(
                'playlist_tracks_' . $playlist_id . '_' . $limit,
                $tracks,
                HOUR_IN_SECONDS,
            );

            return $tracks;
        },
        static fn (string $playlist_id): array => [],
    );

    return $prefilled + $fetched;
}

/**
 * @param array<string> $artist_ids
 * @return array<string, array>
 */
function top_artists_fetch_artists_parallel(string $token, array $artist_ids): array {
    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ];
    $unique_artist_ids = [];

    foreach ($artist_ids as $artist_id) {
        $artist_id = (string) $artist_id;

        if ($artist_id !== '' && ! in_array($artist_id, $unique_artist_ids, true)) {
            $unique_artist_ids[] = $artist_id;
        }
    }

    $prefilled = [];
    $artist_ids_to_fetch = [];

    foreach ($unique_artist_ids as $artist_id) {
        $cached_artist = get_transient('artist_' . $artist_id);

        if (is_array($cached_artist)) {
            $prefilled[$artist_id] = $cached_artist;
            continue;
        }

        $artist_ids_to_fetch[] = $artist_id;
    }

    if ($artist_ids_to_fetch === []) {
        return $prefilled;
    }

    $requests = [];

    foreach (array_chunk($artist_ids_to_fetch, 50) as $index => $chunk) {
        $requests['batch_' . $index] = [
            'url' => add_query_arg(
                ['ids' => implode(',', $chunk)],
                TOP_ARTISTS_API_BASE_URL . '/artists',
            ),
            'headers' => $headers,
            'type' => Requests::GET,
            'options' => [
                'timeout' => TOP_ARTISTS_REQUEST_TIMEOUT,
            ],
        ];
    }

    $fetched_batches = top_artists_request_multiple_json(
        $requests,
        static function (string $batch_key, array $json): array {
            $artists = isset($json['artists']) && is_array($json['artists'])
                ? $json['artists']
                : [];
            $parsed_batch = [];

            foreach ($artists as $artist) {
                if (! is_array($artist)) {
                    continue;
                }

                $artist_id = isset($artist['id']) && is_string($artist['id'])
                    ? $artist['id']
                    : '';

                if ($artist_id === '' || (($artist['type'] ?? '') !== 'artist')) {
                    continue;
                }

                $images = isset($artist['images']) && is_array($artist['images'])
                    ? $artist['images']
                    : [];
                $parsed_artist = [
                    'popularity' => (int) ($artist['popularity'] ?? 0),
                    'name' => (string) ($artist['name'] ?? ''),
                    'image_url' => isset($images[0]['url'])
                        ? (string) $images[0]['url']
                        : '',
                    'spotify_url' => 'https://open.spotify.com/artist/' . $artist_id,
                    'genres' => isset($artist['genres']) && is_array($artist['genres'])
                        ? $artist['genres']
                        : [],
                ];

                set_transient('artist_' . $artist_id, $parsed_artist, 12 * HOUR_IN_SECONDS);
                $parsed_batch[$artist_id] = $parsed_artist;
            }

            return $parsed_batch;
        },
        static fn (string $batch_key): array => [],
    );

    $flattened_artists = [];

    foreach ($fetched_batches as $batch) {
        if (! is_array($batch)) {
            continue;
        }

        foreach ($batch as $artist_id => $artist) {
            if (is_string($artist_id) && is_array($artist)) {
                $flattened_artists[$artist_id] = $artist;
            }
        }
    }

    return $prefilled + $flattened_artists;
}

/**
 * @return array<int, array{artist_id:string, artist_name:string, popularity:int, image_url:string, spotify_url:string}>
 */
function top_artists_get_top_artists_by_genre(string $genre, int $limit = 50): array {
    $limit = max(1, $limit);
    $token = top_artists_get_spotify_token();

    if ($token === '') {
        return [];
    }

    $playlist_ids = top_artists_get_playlists_for_genre($genre);
    $playlist_tracks = top_artists_fetch_playlist_tracks_parallel($token, $playlist_ids, 50);
    $artist_candidates = top_artists_collect_artist_candidates_from_playlist_tracks(
        $playlist_tracks,
        $genre,
    );

    if ($artist_candidates === []) {
        return [];
    }

    $artist_details_by_id = top_artists_fetch_artists_parallel(
        $token,
        array_keys($artist_candidates),
    );
    $rows = top_artists_build_ranked_artist_rows($artist_candidates, $artist_details_by_id, $genre);

    if ($rows === []) {
        return [];
    }

    $trimmed_rows = array_slice($rows, 0, $limit);

    return array_map(
        static function (array $row): array {
            unset($row['score']);

            return $row;
        },
        $trimmed_rows,
    );
}

function top_artists_get_artist_context_by_spotify_id(string $artist_id): array {
    if ($artist_id === '') {
        return [];
    }

    $token = top_artists_get_spotify_token();

    if ($token === '') {
        return [];
    }

    $artists_by_id = top_artists_fetch_artists_parallel($token, [$artist_id]);
    $artist = $artists_by_id[$artist_id] ?? [];

    return is_array($artist) ? $artist : [];
}

function top_artists_get_artist_top_tracks(string $artist_id, int $limit = 10): array {
    if ($artist_id === '') {
        return [];
    }

    $token = top_artists_get_spotify_token();

    if ($token === '') {
        return [];
    }

    $tracks_by_artist_id = top_artists_fetch_artist_top_tracks_parallel($token, [$artist_id]);
    $tracks = $tracks_by_artist_id[$artist_id] ?? [];

    if (! is_array($tracks)) {
        return [];
    }

    return array_slice($tracks, 0, $limit);
}

function top_artists_get_artist_page_url_by_spotify_id(string $artist_id): string {
    if ($artist_id === '') {
        return '';
    }

    $page_id = top_artists_get_existing_artist_page_id($artist_id);

    if ($page_id <= 0) {
        return '';
    }

    $permalink = get_permalink($page_id);

    return is_string($permalink) ? $permalink : '';
}

function top_artists_get_artist_destination_url(array $artist): string {
    $artist_id = isset($artist['artist_id']) ? (string) $artist['artist_id'] : '';

    if ($artist_id !== '') {
        $internal_url = top_artists_get_artist_page_url_by_spotify_id($artist_id);

        if ($internal_url !== '') {
            return $internal_url;
        }
    }

    return isset($artist['spotify_url']) ? (string) $artist['spotify_url'] : '';
}
