<?php

declare(strict_types=1);

function top_artists_build_breadcrumb_schema(array $breadcrumbs): array {
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

function top_artists_build_ranking_item_list_schema_from_artists(
    string $genre_slug,
    string $title,
    array $top_artists,
): ?array {
    if ($top_artists === []) {
        return null;
    }

    $items = [];

    foreach ($top_artists as $index => $artist) {
        if (! is_array($artist)) {
            continue;
        }

        $artist_name = isset($artist['artist_name']) ? (string) $artist['artist_name'] : '';
        $spotify_url = isset($artist['spotify_url']) ? (string) $artist['spotify_url'] : '';
        $artist_image = isset($artist['image_url']) ? (string) $artist['image_url'] : '';

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
            $music_group['sameAs'] = [$spotify_url];
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
