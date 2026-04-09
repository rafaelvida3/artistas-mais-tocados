<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RankingEngineTest extends TestCase {
    public function test_collect_artist_candidates_tracks_score_frequency_and_respects_denylist(): void {
        $playlist_tracks = [
            'playlist-a' => [
                ['artists' => [['id' => 'artist-1', 'name' => 'MC Alpha']]],
                ['artists' => [['id' => 'artist-1', 'name' => 'MC Alpha']]],
                ['artists' => [['id' => '1APqNiQUA2XpwLEbywSWmZ', 'name' => 'Denied']]],
            ],
            'playlist-b' => [
                ['artists' => [['id' => 'artist-2', 'name' => 'MC Beta']]],
            ],
        ];

        $candidates = top_artists_collect_artist_candidates_from_playlist_tracks(
            $playlist_tracks,
            'funk',
        );

        $this->assertSame(2, $candidates['artist-1']['score']);
        $this->assertSame(1, $candidates['artist-2']['score']);
        $this->assertArrayNotHasKey('1APqNiQUA2XpwLEbywSWmZ', $candidates);
    }

    public function test_build_ranked_artist_rows_keeps_final_order_by_popularity(): void {
        $artist_candidates = [
            'artist-1' => [
                'artist_id' => 'artist-1',
                'artist_name' => 'MC Alpha',
                'score' => 3,
            ],
            'artist-2' => [
                'artist_id' => 'artist-2',
                'artist_name' => 'MC Beta',
                'score' => 1,
            ],
        ];
        $artist_details = [
            'artist-1' => [
                'name' => 'MC Alpha',
                'popularity' => 70,
                'image_url' => '',
                'spotify_url' => 'https://open.spotify.com/artist/artist-1',
                'genres' => ['funk carioca'],
            ],
            'artist-2' => [
                'name' => 'MC Beta',
                'popularity' => 90,
                'image_url' => '',
                'spotify_url' => 'https://open.spotify.com/artist/artist-2',
                'genres' => ['funk carioca'],
            ],
        ];

        $rows = top_artists_build_ranked_artist_rows($artist_candidates, $artist_details, 'funk');

        $this->assertSame('artist-2', $rows[0]['artist_id']);
        $this->assertSame(1, $rows[0]['score']);
        $this->assertSame('artist-1', $rows[1]['artist_id']);
        $this->assertSame(3, $rows[1]['score']);
    }
}
