<?php

declare(strict_types=1);

/**
 * Removes accents and normalizes string to lowercase for text comparison.
 */
function normalize_string(string $text): string {
    // remove accents
    $text = transliterator_transliterate('Any-Accents; Latin-ASCII', $text);
    // normalize to lowercase and trim
    return strtolower(trim((string)$text));
}

function get_needles_by_genre(string $genre = ''): array {
    $map = [
        'funk' => [
            'funk carioca','funk br','baile funk','funk consciente','funk de bh','funk melody','brazilian funk'
        ],
        'sertanejo' => [
            'sertanejo','sertanejo universitario','agronejo','modao',
        ],
        'piseiro' => [
            'piseiro','pisadinha','forro','forro eletronico','arrocha'
        ],
        'trap' => [
            'trap brasileiro','trap br','rap brasileiro','hip hop brasileiro', 'brazilian trap', 'trap', 'brazilian hip hop', 'trap funk'
        ],
        'pagode' => [
            'pagode','samba','pagode romantico','samba pagode'
        ]
    ];

    if ($genre !== '') {
        $list = $map[$genre] ?? [];
        if (!is_array($list) || !$list) { return []; }
    } else {
        return array_merge(...array_values($map));
    }
    
    return $list;
}

/**
 * Checks whether the artist profile matches the page genre.
 * Uses artist genres returned from Spotify API (/v1/artists).
 */
function artist_matches_genre(string $genre, array $artist_genres): bool {
    if ($genre === 'geral') {
        return true;
    }

    if (empty($artist_genres)) {
        return false;
    }

    // Normalize needles and artist genres
    $needles = array_map('normalize_string', get_needles_by_genre($genre));
    $normalized_genres = array_map('normalize_string', $artist_genres);

    // Fast path: index 0 match is enough
    if (isset($normalized_genres[0]) && in_array($normalized_genres[0], $needles, true)) {
        return true;
    }

    // If match occurs only at index 1, it must ALSO occur at index 2
    $match_idx1 = isset($normalized_genres[1]) && in_array($normalized_genres[1], $needles, true);
    if ($match_idx1) {
        $match_idx2 = isset($normalized_genres[2]) && in_array($normalized_genres[2], $needles, true);
        return $match_idx2;
    }

    return false;
}

/**
 * Internal denylist by genre.
 * Replace IDs with actual Spotify artist IDs.
 */
function top_artists_internal_denylist(): array {
    return [
        'funk' => [
            '1APqNiQUA2XpwLEbywSWmZ' => true, // Tropa da W&S
            '64DTkZLH6KkkMwZEEZ5VWC' => true // Love Funk
        ],
        'sertanejo' => [
            '0oAZhL6hFrM3YRr6QzjlOf' => true // MJ Records
        ],
        'trap' => [
            '3prRKGJz16RRMRSIM97nHw' => true, // Supernova Ent
            '25XJqeReVV38w0tR04GGBd' => true, // Mainstreet
            '6KHnECmT9Nn73k1tKs62Wu' => true, // HHR,
            '3YVxmhkewoRHu8WFgWlCb7' => true, // NADAMAL
            '7gHzR22tDNSWGS4HkvvPgw' => true, // THE BOX
            '5RYjXDdZ8WSMrjTacFC6Gi' => true // AMUSIK
        ],
        'pagode' => [],
        'piseiro' => [
            '7skt0YXuBGQZr4LGkyTShp' => true, // ÉaBest
        ],
        'geral' => [
            '1APqNiQUA2XpwLEbywSWmZ' => true, // Tropa da W&S
            '64DTkZLH6KkkMwZEEZ5VWC' => true, // Love Funk
            '0oAZhL6hFrM3YRr6QzjlOf' => true, // MJ Records           
            '3prRKGJz16RRMRSIM97nHw' => true, // Supernova Ent
            '25XJqeReVV38w0tR04GGBd' => true, // Mainstreet
            '7gHzR22tDNSWGS4HkvvPgw' => true, // THE BOX
            '5RYjXDdZ8WSMrjTacFC6Gi' => true, // AMUSIK
            '7skt0YXuBGQZr4LGkyTShp' => true, // ÉaBest
        ], // opcional
    ];
}

/**
 * Retorna a denylist efetiva de um gênero (interno + filters para override opcional).
 */
function top_artists_get_denylist_for_genre(string $genre): array {
    $all = top_artists_internal_denylist();
    $base = $all[$genre] ?? [];

    return is_array($base) ? $base : [];
}

/**
 * Consulta a denylist do gênero.
 */
function artist_is_denied_for_genre(string $artist_id, string $genre): bool {
    $deny = top_artists_get_denylist_for_genre($genre);
    return !empty($deny[$artist_id]);
}

function parse_release_date_to_ts(?string $date, ?string $precision): int {
    if (!$date) return 0;
    $p = $precision ?: 'day'; // precision can be 'day', 'month' or 'year'
    if ($p === 'year')  { $date .= '-01-01'; }
    if ($p === 'month') { $date .= '-01'; }
    $ts = strtotime($date);
    return $ts ? (int)$ts : 0;
}

// Quick heuristic for label-like names
function label_name_is_suspect(string $name): bool {
    $n = ' ' . normalize_string($name) . ' ';
    $kw = [
        'entertainment',' ent ',' records ',' record ',' rec ',
        'label',' selo ',' editora ',
        'produtora',' producoes ',' produções ',' productions ',' prod ',
        'estudio',' estudios ',' studio ',' studios ',
        'films',' filmes ',' media ',' midia ',
        'discos',' gravacoes ',' gravações ',
        'topic', 'various artists',' artistas variados ',' v a ',' v.a '
    ];
    foreach ($kw as $k) {
        if (strpos($n, $k) !== false) return true;
    }
    // sufixos/abreviações comuns
    if (preg_match('/\s(ent|rec|prod|prod\.)$/', trim($n))) return true;
    return false;
}

// Count own albums/singles (up to N items; lightweight)
function count_primary_releases(array $albums, int $max_check = 3): int {
    $count = 0;

    foreach ($albums as $album) {
        $group = strtolower((string) ($album['album_group'] ?? ''));
        if ($group === 'album' || $group === 'single') {
            $count++;
            if ($count >= $max_check) {
                break;
            }
        }
    }

    return $count;
}

// Count "appears_on" (co-artist) albums (one page is enough for a strong signal)
function count_appears_on(array $albums, int $limit = 50): int {
    $count = 0;

    foreach ($albums as $album) {
        $group = strtolower((string) ($album['album_group'] ?? ''));
        if ($group === 'appears_on') {
            $count++;
            if ($count >= $limit) {
                break;
            }
        }
    }

    return $count;
}