<?php

declare(strict_types=1);

function top_artists_get_genre_label(string $genre_slug): string {
    $labels = top_artists_get_genre_labels_map();

    return $labels[$genre_slug] ?? ucfirst($genre_slug);
}

function top_artists_build_dynamic_schema_title(string $genre_slug, int $item_count): string {
    if ($genre_slug === 'geral') {
        return 'Top ' . $item_count . ' Artistas no Brasil';
    }

    return 'Top ' . $item_count . ' Artistas de '
        . top_artists_get_genre_label($genre_slug)
        . ' Mais Tocados';
}

function top_artists_get_ranking_schema_title(string $genre_slug): string {
    if ($genre_slug === 'geral') {
        return 'Top 10 Artistas no Brasil';
    }

    return 'Top 10 Artistas de '
        . top_artists_get_genre_label($genre_slug)
        . ' no Brasil';
}
