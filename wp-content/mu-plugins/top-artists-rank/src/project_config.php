<?php

declare(strict_types=1);

/**
 * Central project configuration for ranking, schema and automation rules.
 * Keep static maps here to avoid scattering hardcoded values across files.
 */
function top_artists_get_supported_genres(): array {
    return ['geral', 'funk', 'sertanejo', 'trap', 'piseiro', 'pagode'];
}

/**
 * @return array<string, array<string>>
 */
function top_artists_get_genre_needle_map(): array {
    return [
        'funk' => [
            'funk carioca',
            'funk br',
            'baile funk',
            'funk consciente',
            'funk de bh',
            'funk melody',
            'brazilian funk',
        ],
        'sertanejo' => [
            'sertanejo',
            'sertanejo universitario',
            'agronejo',
            'modao',
        ],
        'piseiro' => [
            'piseiro',
            'pisadinha',
            'forro',
            'forro eletronico',
            'arrocha',
        ],
        'trap' => [
            'trap brasileiro',
            'trap br',
            'rap brasileiro',
            'hip hop brasileiro',
            'brazilian trap',
            'trap',
            'brazilian hip hop',
            'trap funk',
        ],
        'pagode' => [
            'pagode',
            'samba',
            'pagode romantico',
            'samba pagode',
        ],
    ];
}

/**
 * @return array<string, string>
 */
function top_artists_get_genre_labels_map(): array {
    return [
        'funk' => 'Funk',
        'sertanejo' => 'Sertanejo',
        'trap' => 'Trap',
        'piseiro' => 'Piseiro',
        'pagode' => 'Pagode',
        'geral' => 'Brasil',
    ];
}

/**
 * @return array<string, array<string>>
 */
function top_artists_get_curated_playlists_map(): array {
    return [
        'funk' => [
            '3tXQEdXHBCnHHfjGgbNFQ0',
            '1cfmJq1vlOvxkrPRwSl873',
        ],
        'trap' => [
            '3rPugprs5WtLcNH1VqXJDx',
            '3QfUDYpxCgQM77RY7WK890',
        ],
        'sertanejo' => [
            '3rPugprs5WtLcNH1VqXJDx',
            '1KZkxSv0kMXOGYS4lLl2GO',
        ],
        'piseiro' => [
            '3xwHGwphocKJ7vayoFSqCS',
            '7pJUXntr3BMLFFQx4Whkwh',
        ],
        'pagode' => [
            '05341M5VjVdmig3M7NGTOd',
            '7g7vsEvcNEdRDGNuBL4SmY',
        ],
        'geral' => [
            '2UgUYCjD5nuBGyWRaWBoUf',
            '27KCnBs5sZTXgaF4WIy5XQ',
            '1msY3c9fzgE3V11aNhsq2O',
            '4sOqZUOconQiyH55o3Kz7x',
        ],
    ];
}

/**
 * @return array<string, array<string, bool>>
 */
function top_artists_get_internal_denylist_map(): array {
    return [
        'funk' => [
            '1APqNiQUA2XpwLEbywSWmZ' => true,
            '64DTkZLH6KkkMwZEEZ5VWC' => true,
        ],
        'sertanejo' => [
            '0oAZhL6hFrM3YRr6QzjlOf' => true,
        ],
        'trap' => [
            '3prRKGJz16RRMRSIM97nHw' => true,
            '25XJqeReVV38w0tR04GGBd' => true,
            '6KHnECmT9Nn73k1tKs62Wu' => true,
            '3YVxmhkewoRHu8WFgWlCb7' => true,
            '7gHzR22tDNSWGS4HkvvPgw' => true,
            '5RYjXDdZ8WSMrjTacFC6Gi' => true,
        ],
        'pagode' => [],
        'piseiro' => [
            '7skt0YXuBGQZr4LGkyTShp' => true,
        ],
        'geral' => [
            '1APqNiQUA2XpwLEbywSWmZ' => true,
            '64DTkZLH6KkkMwZEEZ5VWC' => true,
            '0oAZhL6hFrM3YRr6QzjlOf' => true,
            '3prRKGJz16RRMRSIM97nHw' => true,
            '25XJqeReVV38w0tR04GGBd' => true,
            '7gHzR22tDNSWGS4HkvvPgw' => true,
            '5RYjXDdZ8WSMrjTacFC6Gi' => true,
            '7skt0YXuBGQZr4LGkyTShp' => true,
        ],
    ];
}

/**
 * @return array<string, array{genre: string}>
 */
function top_artists_get_ranking_pages_map(): array {
    return [
        'artistas-mais-tocados-do-brasil' => ['genre' => 'geral'],
        'artistas-de-funk-mais-tocados' => ['genre' => 'funk'],
        'artistas-de-sertanejo-mais-tocados' => ['genre' => 'sertanejo'],
        'artistas-de-trap-mais-tocados' => ['genre' => 'trap'],
        'artistas-de-piseiro-mais-tocados' => ['genre' => 'piseiro'],
        'artistas-de-pagode-mais-tocados' => ['genre' => 'pagode'],
    ];
}

/**
 * @return string[]
 */
function top_artists_get_refresh_target_slugs(): array {
    return array_keys(top_artists_get_ranking_pages_map());
}
