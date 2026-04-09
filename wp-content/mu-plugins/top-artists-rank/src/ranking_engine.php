<?php

declare(strict_types=1);

/**
 * @param array<string, array> $playlist_tracks
 * @return array<string, array{artist_id: string, artist_name: string, score: int}>
 */
function top_artists_collect_artist_candidates_from_playlist_tracks(
    array $playlist_tracks,
    string $genre,
): array {
    $candidates = [];

    foreach ($playlist_tracks as $tracks) {
        foreach ($tracks as $track) {
            if (! is_array($track) || ! isset($track['artists']) || ! is_array($track['artists'])) {
                continue;
            }

            foreach ($track['artists'] as $artist) {
                if (! is_array($artist) || ! isset($artist['id'], $artist['name'])) {
                    continue;
                }

                $artist_id = (string) $artist['id'];

                if (
                    $artist_id === ''
                    || top_artists_artist_is_denied_for_genre($artist_id, $genre)
                ) {
                    continue;
                }

                if (! isset($candidates[$artist_id])) {
                    $candidates[$artist_id] = [
                        'artist_id' => $artist_id,
                        'artist_name' => (string) $artist['name'],
                        'score' => 0,
                    ];
                }

                $candidates[$artist_id]['score']++;
            }
        }
    }

    return $candidates;
}

/**
 * @param array<string, array{artist_id: string, artist_name: string, score: int}> $artist_candidates
 * @param array<string, array<string, mixed>> $artist_details_by_id
 * @return array<int, array{artist_id: string, artist_name: string, popularity: int, image_url: string, spotify_url: string, score: int}>
 */
function top_artists_build_ranked_artist_rows(
    array $artist_candidates,
    array $artist_details_by_id,
    string $genre,
): array {
    $rows = [];

    foreach ($artist_candidates as $artist_id => $candidate) {
        $artist_name = (string) $candidate['artist_name'];
        $artist_details = $artist_details_by_id[$artist_id] ?? [
            'popularity' => 0,
            'name' => $artist_name,
            'image_url' => '',
            'spotify_url' => 'https://open.spotify.com/artist/' . $artist_id,
            'genres' => [],
        ];
        $genres = isset($artist_details['genres']) && is_array($artist_details['genres'])
            ? $artist_details['genres']
            : [];

        if (! top_artists_artist_matches_genre($genre, $genres)) {
            continue;
        }

        $rows[] = [
            'artist_id' => $artist_id,
            'artist_name' => (string) (($artist_details['name'] ?? '') ?: $artist_name),
            'popularity' => (int) ($artist_details['popularity'] ?? 0),
            'image_url' => (string) ($artist_details['image_url'] ?? ''),
            'spotify_url' => (string) ($artist_details['spotify_url'] ?? ''),
            'score' => (int) ($candidate['score']),
        ];
    }

    usort(
        $rows,
        static function (array $left, array $right): int {
            $popularity_comparison = $right['popularity'] <=> $left['popularity'];

            if ($popularity_comparison !== 0) {
                return $popularity_comparison;
            }

            return strcmp((string) $left['artist_name'], (string) $right['artist_name']);
        },
    );

    return $rows;
}
