<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('rank_math/json_ld', 'filter_top_artists_schema_graph', 99, 2);

function filter_top_artists_schema_graph(array $data, mixed $jsonld): array {
    if (is_admin()) {
        return $data;
    }

    $ranking_context = get_ranking_schema_context();

    if ($ranking_context !== null) {
        $data = remove_breadcrumb_list_schema($data);
        $data[] = build_breadcrumb_schema($ranking_context['breadcrumbs']);

        if ($ranking_context['mode'] === 'home') {
            $genres = ['geral', 'funk', 'sertanejo', 'trap', 'piseiro', 'pagode'];

            foreach ($genres as $genre_slug) {
                $schema = build_ranking_item_list_schema(
                    $genre_slug,
                    get_ranking_schema_title($genre_slug),
                    10
                );

                if ($schema !== null) {
                    $data[] = $schema;
                }
            }
        }

        if ($ranking_context['mode'] === 'genre') {
            $schema = build_ranking_item_list_schema(
                $ranking_context['genre'],
                $ranking_context['title'],
                50
            );

            if ($schema !== null) {
                $data[] = $schema;
            }
        }

        return array_values($data);
    }

    $artist_context = get_artist_schema_context();

    if ($artist_context === null) {
        return $data;
    }

    $data = remove_breadcrumb_list_schema($data);
    $data[] = build_breadcrumb_schema($artist_context['breadcrumbs']);
    $data[] = build_artist_music_group_schema($artist_context);

    $top_tracks_schema = build_artist_top_tracks_schema($artist_context);

    if ($top_tracks_schema !== null) {
        $data[] = $top_tracks_schema;
    }

    return array_values($data);
}

function get_ranking_schema_context(): ?array {
    if (is_front_page()) {
        return [
            'mode' => 'home',
            'breadcrumbs' => [
                [
                    'name' => 'Início',
                    'url' => home_url('/'),
                ],
            ],
        ];
    }

    $ranking_pages = [
        'artistas-mais-tocados-do-brasil' => [
            'genre' => 'geral',
        ],
        'artistas-de-funk-mais-tocados' => [
            'genre' => 'funk',
        ],
        'artistas-de-sertanejo-mais-tocados' => [
            'genre' => 'sertanejo',
        ],
        'artistas-de-trap-mais-tocados' => [
            'genre' => 'trap',
        ],
        'artistas-de-piseiro-mais-tocados' => [
            'genre' => 'piseiro',
        ],
        'artistas-de-pagode-mais-tocados' => [
            'genre' => 'pagode',
        ],
    ];

    foreach ($ranking_pages as $page_slug => $page_data) {
        if (!is_page($page_slug)) {
            continue;
        }

        if (!function_exists('get_top_artists_by_genre')) {
            return null;
        }

        $top_artists = get_top_artists_by_genre($page_data['genre'], 50);
        $item_count = is_array($top_artists) ? count_valid_artists($top_artists) : 0;
        $schema_title = build_dynamic_schema_title($page_data['genre'], $item_count);

        return [
            'mode' => 'genre',
            'genre' => $page_data['genre'],
            'title' => $schema_title,
            'breadcrumbs' => [
                [
                    'name' => 'Início',
                    'url' => home_url('/'),
                ],
                [
                    'name' => $schema_title,
                    'url' => get_permalink(),
                ],
            ],
        ];
    }

    return null;
}

function get_artist_schema_context(): ?array {
    $post_id = get_queried_object_id();

    if ($post_id <= 0) {
        return null;
    }

    if (!function_exists('get_current_artist_spotify_id')) {
        return null;
    }

    $artist_id = get_current_artist_spotify_id($post_id);

    if ($artist_id === '') {
        return null;
    }

    $artist_name = get_the_title($post_id);
    $artist_name = is_string($artist_name) ? trim($artist_name) : '';

    if ($artist_name === '') {
        return null;
    }

    $artist_page_url = get_permalink($post_id);
    $artist_page_url = is_string($artist_page_url) ? $artist_page_url : '';

    if ($artist_page_url === '') {
        return null;
    }

    $artist_context = function_exists('get_artist_context_by_spotify_id')
        ? get_artist_context_by_spotify_id($artist_id)
        : [];

    $top_tracks = function_exists('get_artist_top_tracks')
        ? get_artist_top_tracks($artist_id, 10)
        : [];

    $spotify_url = isset($artist_context['spotify_url']) ? (string) $artist_context['spotify_url'] : '';
    $image_url = isset($artist_context['image_url']) ? esc_url_raw((string) $artist_context['image_url']) : '';

    return [
        'post_id' => $post_id,
        'artist_id' => $artist_id,
        'artist_name' => $artist_name,
        'artist_page_url' => $artist_page_url,
        'spotify_url' => $spotify_url,
        'image_url' => $image_url,
        'top_tracks' => is_array($top_tracks) ? $top_tracks : [],
        'breadcrumbs' => [
            [
                'name' => 'Início',
                'url' => home_url('/'),
            ],
            [
                'name' => 'Artistas',
                'url' => home_url('/artista/'),
            ],
            [
                'name' => $artist_name,
                'url' => $artist_page_url,
            ],
        ],
    ];
}

function count_valid_artists(array $top_artists): int {
    $count = 0;

    foreach ($top_artists as $artist) {
        $artist_name = isset($artist['artist_name']) ? trim((string) $artist['artist_name']) : '';

        if ($artist_name !== '') {
            $count++;
        }
    }

    return $count;
}

function get_genre_label(string $genre_slug): string {
    $labels = [
        'funk' => 'Funk',
        'sertanejo' => 'Sertanejo',
        'trap' => 'Trap',
        'piseiro' => 'Piseiro',
        'pagode' => 'Pagode',
        'geral' => 'Brasil',
    ];

    return $labels[$genre_slug] ?? ucfirst($genre_slug);
}

function build_dynamic_schema_title(string $genre_slug, int $item_count): string {
    if ($genre_slug === 'geral') {
        return 'Top ' . $item_count . ' Artistas no Brasil';
    }

    return 'Top ' . $item_count . ' Artistas de ' . get_genre_label($genre_slug) . ' Mais Tocados';
}

function get_ranking_schema_title(string $genre_slug): string {
    if ($genre_slug === 'geral') {
        return 'Top 10 Artistas no Brasil';
    }

    return 'Top 10 Artistas de ' . get_genre_label($genre_slug) . ' no Brasil';
}

function remove_breadcrumb_list_schema(array $data): array {
    foreach ($data as $key => $schema) {
        if (
            is_array($schema) &&
            isset($schema['@type']) &&
            $schema['@type'] === 'BreadcrumbList'
        ) {
            unset($data[$key]);
        }
    }

    return $data;
}

function build_breadcrumb_schema(array $breadcrumbs): array {
    $items = [];

    foreach ($breadcrumbs as $index => $breadcrumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $breadcrumb['name'],
            'item' => [
                '@id' => $breadcrumb['url'],
            ],
        ];
    }

    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
}

function build_ranking_item_list_schema(string $genre_slug, string $title, int $limit): ?array {
    if (!function_exists('get_top_artists_by_genre')) {
        return null;
    }

    $top_artists = get_top_artists_by_genre($genre_slug, $limit);

    if (empty($top_artists) || !is_array($top_artists)) {
        return null;
    }

    $items = [];

    foreach ($top_artists as $index => $artist) {
        $artist_name = isset($artist['artist_name']) ? (string) $artist['artist_name'] : '';
        $spotify_url = isset($artist['spotify_url']) ? (string) $artist['spotify_url'] : '';
        $artist_image = isset($artist['image_url'])
            ? esc_url_raw((string) $artist['image_url'])
            : '';

        if ($artist_name === '') {
            continue;
        }

        $music_group = [
            '@type' => 'MusicGroup',
            'name' => $artist_name,
        ];

        if ($genre_slug !== 'geral') {
            $music_group['genre'] = $genre_slug;
        }

        if ($artist_image !== '') {
            $music_group['image'] = $artist_image;
        }

        if ($spotify_url !== '') {
            $music_group['sameAs'] = [esc_url_raw($spotify_url)];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'item' => $music_group,
        ];
    }

    if ($items === []) {
        return null;
    }

    return [
        '@type' => 'ItemList',
        'name' => $title,
        'itemListOrder' => 'Descending',
        'numberOfItems' => count($items),
        'itemListElement' => $items,
    ];
}

function build_artist_music_group_schema(array $artist_context): array {
    $schema = [
        '@type' => 'MusicGroup',
        'name' => $artist_context['artist_name'],
        'url' => $artist_context['artist_page_url'],
    ];

    if ($artist_context['image_url'] !== '') {
        $schema['image'] = $artist_context['image_url'];
    }

    if ($artist_context['spotify_url'] !== '') {
        $schema['sameAs'] = [
            esc_url_raw($artist_context['spotify_url']),
        ];
    }

    return $schema;
}

function build_artist_top_tracks_schema(array $artist_context): ?array {
    $top_tracks = $artist_context['top_tracks'] ?? [];

    if (!is_array($top_tracks) || $top_tracks === []) {
        return null;
    }

    $items = [];
    $position = 1;

    foreach ($top_tracks as $track) {
        if (!is_array($track)) {
            continue;
        }

        $track_name = isset($track['name']) ? trim((string) $track['name']) : '';

        if ($track_name === '') {
            continue;
        }

        $track_url = isset($track['external_urls']['spotify'])
            ? esc_url_raw((string) $track['external_urls']['spotify'])
            : '';

        $album_name = isset($track['album']['name'])
            ? trim((string) $track['album']['name'])
            : '';

        $music_recording = [
            '@type' => 'MusicRecording',
            'name' => $track_name,
            'byArtist' => [
                '@type' => 'MusicGroup',
                'name' => $artist_context['artist_name'],
            ],
        ];

        if ($track_url !== '') {
            $music_recording['url'] = $track_url;
        }

        if ($album_name !== '') {
            $music_recording['inAlbum'] = [
                '@type' => 'MusicAlbum',
                'name' => $album_name,
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'item' => $music_recording,
        ];

        $position++;
    }

    if ($items === []) {
        return null;
    }

    return [
        '@type' => 'ItemList',
        'name' => 'Top 10 músicas de ' . $artist_context['artist_name'],
        'itemListOrder' => 'Descending',
        'numberOfItems' => count($items),
        'itemListElement' => $items,
    ];
}