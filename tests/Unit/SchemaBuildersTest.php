<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchemaBuildersTest extends TestCase {
    public function test_build_ranking_item_list_schema_from_artists_returns_expected_shape(): void {
        $artists = [
            [
                'artist_name' => 'MC Alpha',
                'spotify_url' => 'https://open.spotify.com/artist/alpha',
                'image_url' => 'https://cdn.test/alpha.jpg',
            ],
            [
                'artist_name' => 'MC Beta',
                'spotify_url' => 'https://open.spotify.com/artist/beta',
                'image_url' => '',
            ],
        ];

        $schema = top_artists_build_ranking_item_list_schema_from_artists(
            'funk',
            'Top 2 Artistas de Funk Mais Tocados',
            $artists,
        );

        $this->assertSame('ItemList', $schema['@type']);
        $this->assertSame(2, $schema['numberOfItems']);
        $this->assertSame('MC Alpha', $schema['itemListElement'][0]['item']['name']);
        $this->assertSame('funk', $schema['itemListElement'][0]['item']['genre']);
        $this->assertSame(
            'https://open.spotify.com/artist/alpha',
            $schema['itemListElement'][0]['item']['sameAs'][0]
        );
    }
}
