<?php

declare(strict_types=1);

/**
 * This file depends on ext-intl because it uses transliterator_transliterate()
 * for accent-insensitive comparisons.
 */
function top_artists_normalize_string(string $text): string {
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Accents; Latin-ASCII', $text);
    }

    return strtolower(trim((string) $text));
}

function top_artists_get_needles_by_genre(string $genre = ''): array {
    $map = top_artists_get_genre_needle_map();

    if ($genre !== '') {
        $list = $map[$genre] ?? [];

        return $list;
    }

    return array_merge(...array_values($map));
}

/**
 * Secondary matches are treated as weak signals.
 * When index 1 matches, index 2 must also confirm the genre.
 *
 * @param array<int, string> $normalized_genres
 * @param array<int, string> $needles
 */
function top_artists_has_confirmed_secondary_genre_match(
    array $normalized_genres,
    array $needles,
): bool {
    $matches_secondary = isset($normalized_genres[1])
        && in_array($normalized_genres[1], $needles, true);

    if (! $matches_secondary) {
        return false;
    }

    return isset($normalized_genres[2])
        && in_array($normalized_genres[2], $needles, true);
}

/**
 * Checks whether the artist profile matches the page genre.
 * Uses artist genres returned from Spotify API (/v1/artists).
 */
function top_artists_artist_matches_genre(string $genre, array $artist_genres): bool {
    if ($genre === 'geral') {
        return true;
    }

    if ($artist_genres === []) {
        return false;
    }

    $needles = array_map('top_artists_normalize_string', top_artists_get_needles_by_genre($genre));
    $normalized_genres = array_map('top_artists_normalize_string', $artist_genres);

    if (isset($normalized_genres[0]) && in_array($normalized_genres[0], $needles, true)) {
        return true;
    }

    return top_artists_has_confirmed_secondary_genre_match($normalized_genres, $needles);
}

/**
 * @return array<string, bool>
 */
function top_artists_get_denylist_for_genre(string $genre): array {
    $all = top_artists_get_internal_denylist_map();
    $base = $all[$genre] ?? [];

    return $base;
}

function top_artists_artist_is_denied_for_genre(string $artist_id, string $genre): bool {
    $denylist = top_artists_get_denylist_for_genre($genre);

    return ! empty($denylist[$artist_id]);
}

function top_artists_parse_release_date_to_ts(?string $date, ?string $precision): int {
    if (! $date) {
        return 0;
    }

    $resolved_precision = $precision ?: 'day';

    if ($resolved_precision === 'year') {
        $date .= '-01-01';
    }

    if ($resolved_precision === 'month') {
        $date .= '-01';
    }

    $timestamp = strtotime($date);

    return $timestamp ? (int) $timestamp : 0;
}

function top_artists_label_name_is_suspect(string $name): bool {
    $normalized_name = ' ' . top_artists_normalize_string($name) . ' ';
    $keywords = [
        'entertainment',
        ' ent ',
        ' records ',
        ' record ',
        ' rec ',
        'label',
        ' selo ',
        ' editora ',
        'produtora',
        ' producoes ',
        ' produções ',
        ' productions ',
        ' prod ',
        'estudio',
        ' estudios ',
        ' studio ',
        ' studios ',
        'films',
        ' filmes ',
        ' media ',
        ' midia ',
        'discos',
        ' gravacoes ',
        ' gravações ',
        'topic',
        'various artists',
        ' artistas variados ',
        ' v a ',
        ' v.a ',
    ];

    foreach ($keywords as $keyword) {
        if (strpos($normalized_name, $keyword) !== false) {
            return true;
        }
    }

    return (bool) preg_match('/\s(ent|rec|prod|prod\.)$/', trim($normalized_name));
}

function top_artists_count_primary_releases(array $albums, int $max_check = 3): int {
    $count = 0;

    foreach ($albums as $album) {
        $group = strtolower((string) ($album['album_group'] ?? ''));

        if ($group !== 'album' && $group !== 'single') {
            continue;
        }

        $count++;

        if ($count >= $max_check) {
            break;
        }
    }

    return $count;
}

function top_artists_count_appears_on(array $albums, int $limit = 50): int {
    $count = 0;

    foreach ($albums as $album) {
        $group = strtolower((string) ($album['album_group'] ?? ''));

        if ($group !== 'appears_on') {
            continue;
        }

        $count++;

        if ($count >= $limit) {
            break;
        }
    }

    return $count;
}
