<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../');
}

$GLOBALS['top_artists_test_filter_registrations'] = [];
$GLOBALS['top_artists_test_is_front_page'] = false;
$GLOBALS['top_artists_test_rankings'] = [];

if (! function_exists('add_filter')) {
    function add_filter(string $hook_name, string $callback, int $priority = 10, int $accepted_args = 1): void {
        $GLOBALS['top_artists_test_filter_registrations'][] = [
            'hook_name' => $hook_name,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool {
        return false;
    }
}

if (! function_exists('is_front_page')) {
    function is_front_page(): bool {
        return (bool) ($GLOBALS['top_artists_test_is_front_page'] ?? false);
    }
}

if (! function_exists('is_page')) {
    function is_page(string $page_slug): bool {
        unset($page_slug);

        return false;
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string {
        return 'https://example.test' . $path;
    }
}

if (! function_exists('top_artists_get_top_artists_by_genre')) {
    function top_artists_get_top_artists_by_genre(string $genre_slug, int $limit = 50): array {
        $rankings = $GLOBALS['top_artists_test_rankings'] ?? [];
        $artists = $rankings[$genre_slug] ?? [];

        return array_slice($artists, 0, $limit);
    }
}

require_once __DIR__ . '/../../wp-content/mu-plugins/top-artists-rank/includes/schema.php';

final class SchemaFilterIntegrationTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['top_artists_test_is_front_page'] = false;
        $GLOBALS['top_artists_test_rankings'] = $this->build_rankings_fixture();
    }

    public function test_schema_file_registers_rank_math_filter(): void {
        $this->assertContains(
            [
                'hook_name' => 'rank_math/json_ld',
                'callback' => 'top_artists_filter_schema_graph',
                'priority' => 99,
                'accepted_args' => 2,
            ],
            $GLOBALS['top_artists_test_filter_registrations']
        );
    }

    public function test_home_schema_filter_replaces_default_breadcrumb_and_adds_item_lists(): void {
        $GLOBALS['top_artists_test_is_front_page'] = true;

        $base_graph = [
            [
                '@type' => 'Organization',
                'name' => 'Artistas Mais Tocados',
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Legacy breadcrumb',
                        'item' => [
                            '@id' => 'https://example.test/legacy',
                        ],
                    ],
                ],
            ],
        ];

        $result = top_artists_filter_schema_graph($base_graph, null);

        $breadcrumb_schemas = array_values(
            array_filter(
                $result,
                static fn (array $schema): bool => ($schema['@type'] ?? null) === 'BreadcrumbList'
            )
        );

        $item_list_schemas = array_values(
            array_filter(
                $result,
                static fn (array $schema): bool => ($schema['@type'] ?? null) === 'ItemList'
            )
        );

        $this->assertCount(1, $breadcrumb_schemas);
        $this->assertSame('Início', $breadcrumb_schemas[0]['itemListElement'][0]['name']);
        $this->assertSame(
            'https://example.test/',
            $breadcrumb_schemas[0]['itemListElement'][0]['item']['@id']
        );
        $this->assertCount(count(top_artists_get_supported_genres()), $item_list_schemas);
        $this->assertContains('Top 10 Artistas no Brasil', array_column($item_list_schemas, 'name'));
        $this->assertContains('Top 10 Artistas de Funk no Brasil', array_column($item_list_schemas, 'name'));
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function build_rankings_fixture(): array {
        $rankings = [];

        foreach (top_artists_get_supported_genres() as $genre_slug) {
            $suffix = $genre_slug === 'geral' ? 'Brazil' : ucfirst($genre_slug);

            $rankings[$genre_slug] = [
                [
                    'artist_name' => 'Artist ' . $suffix,
                    'spotify_url' => 'https://open.spotify.com/artist/' . $genre_slug,
                    'image_url' => 'https://cdn.test/' . $genre_slug . '.jpg',
                ],
            ];
        }

        return $rankings;
    }
}
