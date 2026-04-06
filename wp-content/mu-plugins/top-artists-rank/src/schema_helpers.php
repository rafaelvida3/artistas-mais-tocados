<?php

declare(strict_types=1);

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