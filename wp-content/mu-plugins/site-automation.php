<?php

declare(strict_types=1);

/**
 * MU Plugin Name: Artist Pages Generator
 * Author: Rafael Vida
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/common/post-updates.php';
require_once __DIR__ . '/top-artists-rank/src/project_config.php';
require_once __DIR__ . '/top-artists-rank/src/automation_helpers.php';

const ARTIST_PAGES_BATCH_SIZE = 10;
const ARTIST_PAGES_PARENT_SLUG = 'artista';
const ARTIST_PAGES_QUEUE_OPTION = 'artist_queue';
const ARTIST_PAGES_OFFSET_OPTION = 'artist_offset';
const ARTIST_PAGES_LOCK_TRANSIENT = 'artist_lock';
const ARTIST_PAGES_LOCK_TTL = 120;

function artist_pages_log_error(string $message, array $context = []): void {
    $prefix = '[artist-pages] ';

    if ($context !== []) {
        $json_context = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $message .= ' :: ' . ($json_context ?: '');
    }

    error_log($prefix . $message);
}

function artist_pages_ensure_parent_page(): int {
    $page = get_page_by_path(ARTIST_PAGES_PARENT_SLUG);

    if ($page instanceof \WP_Post) {
        return (int) $page->ID;
    }

    $content = <<<'HTML'

<!-- wp:paragraph -->
<p>Nesta seção, você encontra páginas dedicadas aos artistas mais tocados, com destaques, músicas mais populares e links para ouvir os principais sucessos.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Se você quer explorar os nomes que mais se destacam no momento, vale conferir também nossa página de <a href="/artistas-mais-tocados-do-brasil">artistas mais tocados do Brasil</a>, com uma visão geral dos rankings e tendências atuais.</p>
<!-- /wp:paragraph -->
HTML;

    $inserted_page_id = wp_insert_post([
        'post_title' => 'Artistas',
        'post_name' => ARTIST_PAGES_PARENT_SLUG,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => $content,
    ], true);

    if ($inserted_page_id instanceof \WP_Error) {
        artist_pages_log_error('Failed to create artist parent page', [
            'slug' => ARTIST_PAGES_PARENT_SLUG,
            'error_message' => $inserted_page_id->get_error_message(),
            'error_data' => $inserted_page_id->get_error_data(),
        ]);

        return 0;
    }

    $page_id = (int) $inserted_page_id;
    update_post_meta($page_id, 'rank_math_rich_snippet', 'off');

    return $page_id;
}

/**
 * @return string[]
 */
function artist_pages_get_seed_genres(): array {
    return top_artists_get_supported_genres();
}

function top_artists_get_existing_artist_page_id(string $spotify_id): int {
    $pages = get_posts([
        'post_type' => 'page',
        'fields' => 'ids',
        'meta_key' => 'artist_spotify_id',
        'meta_value' => $spotify_id,
        'numberposts' => 1,
    ]);

    return $pages ? (int) $pages[0] : 0;
}

/**
 * @return array<int, array<string, mixed>>
 */
function artist_pages_get_all_ranked_artists(): array {
    if (! function_exists('top_artists_get_top_artists_by_genre')) {
        return [];
    }

    $artists_by_id = [];

    foreach (artist_pages_get_seed_genres() as $genre) {
        $ranked_artists = top_artists_get_top_artists_by_genre($genre, 50);

        if (! is_array($ranked_artists)) {
            continue;
        }

        foreach ($ranked_artists as $artist) {
            if (! is_array($artist)) {
                continue;
            }

            $artist_id = isset($artist['artist_id']) ? (string) $artist['artist_id'] : '';

            if ($artist_id === '') {
                continue;
            }

            $artists_by_id[$artist_id] = $artist;
        }
    }

    return array_values($artists_by_id);
}

function artist_pages_get_spotify_url(array $artist): string {
    if (isset($artist['spotify_url']) && is_string($artist['spotify_url']) && $artist['spotify_url'] !== '') {
        return $artist['spotify_url'];
    }

    $spotify_url = $artist['external_urls']['spotify'] ?? '';

    return is_string($spotify_url) ? $spotify_url : '';
}

function artist_pages_build_blocks_content(): string {
    return <<<'HTML'
<!-- wp:columns {"align":"wide","verticalAlignment":"center","className":"ranking-layout","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center ranking-layout">
    <!-- wp:column {"width":"33.33%","className":"ranking-list"} -->
    <div class="wp-block-column ranking-list" style="flex-basis:33.33%">
        <!-- wp:heading {"textAlign":"center","className":"text-nowrap","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"palette-color-1", "textColor": "palette-color-8"} -->
        <h2 class="wp-block-heading has-text-align-center text-nowrap has-palette-color-8-color has-text-color has-background has-link-color" style="background:linear-gradient(0deg,rgb(40,161,101) 0%,rgb(52,195,121) 100%);margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
            Top 10
        </h2>
        <!-- /wp:heading -->

        <!-- wp:shortcode -->
        [artist_top_tracks limit="10"]
        <!-- /wp:shortcode -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"66.66%","className":"ranking-highlight"} -->
    <div class="wp-block-column ranking-highlight" style="flex-basis:66.66%">
        <!-- wp:shortcode -->
        [artist_featured]
        <!-- /wp:shortcode -->
    </div>
    <!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:shortcode -->
[artist_seo_footer]
<!-- /wp:shortcode -->
HTML;
}

function artist_pages_create_page(array $artist, int $parent_id): int {
    $artist_id = isset($artist['artist_id']) ? (string) $artist['artist_id'] : '';
    $artist_name = isset($artist['artist_name']) ? (string) $artist['artist_name'] : '';

    if ($artist_id === '' || $artist_name === '' || $parent_id <= 0) {
        artist_pages_log_error('Invalid artist data for page creation', [
            'artist_id' => $artist_id,
            'artist_name' => $artist_name,
            'parent_id' => $parent_id,
        ]);

        return 0;
    }

    if (top_artists_get_existing_artist_page_id($artist_id) > 0) {
        return 0;
    }

    $inserted_post_id = wp_insert_post([
        'post_title' => $artist_name,
        'post_name' => sanitize_title($artist_name),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $parent_id,
        'post_content' => artist_pages_build_blocks_content(),
    ], true);

    if ($inserted_post_id instanceof \WP_Error) {
        artist_pages_log_error('Failed to create artist page', [
            'artist_id' => $artist_id,
            'artist_name' => $artist_name,
            'parent_id' => $parent_id,
            'error_message' => $inserted_post_id->get_error_message(),
            'error_data' => $inserted_post_id->get_error_data(),
        ]);

        return 0;
    }

    $post_id = (int) $inserted_post_id;
    update_post_meta($post_id, 'artist_spotify_id', $artist_id);

    $spotify_url = artist_pages_get_spotify_url($artist);

    if ($spotify_url !== '') {
        update_post_meta($post_id, 'artist_spotify_url', esc_url_raw($spotify_url));
    }

    $artist_name_lower = mb_strtolower($artist_name, 'UTF-8');
    $keywords = [
        $artist_name_lower,
        $artist_name_lower . ' spotify',
        $artist_name_lower . ' músicas',
        $artist_name_lower . ' músicas mais tocadas',
        $artist_name_lower . ' músicas mais ouvidas',
    ];

    update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $keywords));
    update_post_meta($post_id, 'rank_math_title', $artist_name . ' %sep% Músicas mais tocadas no Spotify');
    update_post_meta(
        $post_id,
        'rank_math_description',
        'Veja as músicas mais tocadas de ' . $artist_name . ' no Spotify, com links diretos para ouvir cada faixa.',
    );
    update_post_meta($post_id, 'rank_math_rich_snippet', 'off');

    return $post_id;
}

function artist_pages_seed_queue(): void {
    update_option(ARTIST_PAGES_QUEUE_OPTION, artist_pages_get_all_ranked_artists());
    update_option(ARTIST_PAGES_OFFSET_OPTION, 0);
}

function artist_pages_has_valid_cron_key(): bool {
    if (! defined('CRON_SECRET')) {
        return false;
    }

    $key = $_GET['key'] ?? '';

    return is_string($key) && $key !== '' && hash_equals(CRON_SECRET, $key);
}

function artist_pages_handle_seed_request(): void {
    if (! isset($_GET['artist_pages_seed'])) {
        return;
    }

    if (! artist_pages_has_valid_cron_key()) {
        status_header(403);
        exit('Forbidden');
    }

    artist_pages_seed_queue();
    exit('Seed OK');
}

add_action('init', 'artist_pages_handle_seed_request', 999);

function artist_pages_handle_process_request(): void {
    if (! isset($_GET['artist_pages_process'])) {
        return;
    }

    if (! artist_pages_has_valid_cron_key()) {
        status_header(403);
        exit('Forbidden');
    }

    if (top_artists_is_batch_lock_active(get_transient(ARTIST_PAGES_LOCK_TRANSIENT))) {
        exit('Locked');
    }

    set_transient(ARTIST_PAGES_LOCK_TRANSIENT, 1, ARTIST_PAGES_LOCK_TTL);

    $queue = get_option(ARTIST_PAGES_QUEUE_OPTION, []);
    $offset = (int) get_option(ARTIST_PAGES_OFFSET_OPTION, 0);
    $has_changes = false;

    if (! is_array($queue) || $queue === []) {
        delete_transient(ARTIST_PAGES_LOCK_TRANSIENT);
        exit('Empty');
    }

    $parent_id = artist_pages_ensure_parent_page();

    if ($parent_id <= 0) {
        artist_pages_log_error('Artist parent page could not be resolved during batch processing', [
            'offset' => $offset,
            'queue_count' => count($queue),
        ]);

        delete_transient(ARTIST_PAGES_LOCK_TRANSIENT);
        exit('Parent page error');
    }

    $batch_window = top_artists_build_batch_window($queue, $offset, ARTIST_PAGES_BATCH_SIZE);
    $batch = $batch_window['batch'];

    foreach ($batch as $artist) {
        try {
            if (! is_array($artist)) {
                continue;
            }

            $artist_id = isset($artist['artist_id']) ? (string) $artist['artist_id'] : '';

            if ($artist_id === '') {
                continue;
            }

            $existing_id = top_artists_get_existing_artist_page_id($artist_id);

            if ($existing_id > 0) {
                top_artists_update_post_modified_date($existing_id);
                $has_changes = true;
                continue;
            }

            $created_post_id = artist_pages_create_page($artist, $parent_id);

            if ($created_post_id > 0) {
                $has_changes = true;
            }
        } catch (\Throwable $throwable) {
            artist_pages_log_error('Unexpected exception during artist page creation', [
                'artist_id' => $artist['artist_id'] ?? '',
                'artist_name' => $artist['artist_name'] ?? '',
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        }
    }

    if ($has_changes && class_exists('\RankMath\Sitemap\Cache')) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }

    $offset = $batch_window['next_offset'];
    update_option(ARTIST_PAGES_OFFSET_OPTION, $offset);

    if ($batch_window['is_complete']) {
        delete_option(ARTIST_PAGES_QUEUE_OPTION);
        delete_option(ARTIST_PAGES_OFFSET_OPTION);
        delete_transient(ARTIST_PAGES_LOCK_TRANSIENT);
        exit('Done');
    }

    delete_transient(ARTIST_PAGES_LOCK_TRANSIENT);
    exit('Processed ' . $offset);
}

add_action('init', 'artist_pages_handle_process_request', 999);
