<?php

declare(strict_types=1);

/**
 * MU Plugin Name: Artist Pages Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/common/post-updates.php';

const ARTIST_BATCH_SIZE = 10;
const ARTIST_PARENT_SLUG = 'artista';
const ARTIST_QUEUE_OPTION = 'artist_queue';
const ARTIST_OFFSET_OPTION = 'artist_offset';
const ARTIST_LOCK_TRANSIENT = 'artist_lock';
const ARTIST_LOCK_TTL = 120;

function log_artist_pages_error(string $message, array $context = []): void {
    $prefix = '[artist-pages] ';

    if ($context) {
        $json_context = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $message .= ' :: ' . ($json_context ?: '');
    }

    error_log($prefix . $message);
}

/**
 * Ensure parent page exists
 */
function ensure_artist_parent_page(): int {
    $page = get_page_by_path(ARTIST_PARENT_SLUG);

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
        'post_name' => ARTIST_PARENT_SLUG,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => $content,
    ], true);

    if ($inserted_page_id instanceof \WP_Error) {
        log_artist_pages_error('Failed to create artist parent page', [
            'slug' => ARTIST_PARENT_SLUG,
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
 * Genres used for seed
 */
function get_seed_genres(): array {
    return ['geral', 'funk', 'sertanejo', 'trap', 'piseiro', 'pagode'];
}

/**
 * Get all artists from rankings (deduplicated)
 */
function get_all_ranked_artists(): array {
    if (!function_exists('get_top_artists_by_genre')) {
        return [];
    }

    $artists = [];

    foreach (get_seed_genres() as $genre) {
        $list = get_top_artists_by_genre($genre, 50);

        if (!is_array($list)) continue;

        foreach ($list as $artist) {
            if (!is_array($artist)) continue;

            $id = $artist['artist_id'] ?? '';

            if (!$id) continue;

            $artists[$id] = $artist;
        }
    }

    return array_values($artists);
}

/**
 * Find existing page
 */
function get_existing_artist_page_id(string $spotify_id): int {
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
 * Get artist Spotify URL
 */
function get_artist_spotify_url(array $artist): string {
    if (isset($artist['spotify_url']) && is_string($artist['spotify_url']) && $artist['spotify_url'] !== '') {
        return $artist['spotify_url'];
    }

    $spotify_url = $artist['external_urls']['spotify'] ?? '';

    return is_string($spotify_url) ? $spotify_url : '';
}

function build_artist_page_blocks_content(): string {
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

/**
 * Create page
 */
function create_artist_page(array $artist, int $parent_id): int {
    $id = isset($artist['artist_id']) ? (string) $artist['artist_id'] : '';
    $name = isset($artist['artist_name']) ? (string) $artist['artist_name'] : '';

    if ($id === '' || $name === '' || $parent_id <= 0) {
        log_artist_pages_error('Invalid artist data for page creation', [
            'artist_id' => $id,
            'artist_name' => $name,
            'parent_id' => $parent_id,
        ]);
        return 0;
    }

    if (get_existing_artist_page_id($id) > 0) {
        return 0;
    }

    $content = build_artist_page_blocks_content();

    $inserted_post_id = wp_insert_post([
        'post_title' => $name,
        'post_name' => sanitize_title($name),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $parent_id,
        'post_content' => $content,
    ], true);

    if ($inserted_post_id instanceof \WP_Error) {
        log_artist_pages_error('Failed to create artist page', [
            'artist_id' => $id,
            'artist_name' => $name,
            'parent_id' => $parent_id,
            'error_message' => $inserted_post_id->get_error_message(),
            'error_data' => $inserted_post_id->get_error_data(),
        ]);
        return 0;
    }

    $post_id = (int) $inserted_post_id;

    update_post_meta($post_id, 'artist_spotify_id', $id);

    $spotify_url = get_artist_spotify_url($artist);

    if ($spotify_url !== '') {
        update_post_meta($post_id, 'artist_spotify_url', esc_url_raw($spotify_url));
    }

    $name_lower = mb_strtolower($name, 'UTF-8');

    $keywords = [
        $name_lower,
        $name_lower . ' spotify',
        $name_lower . ' músicas',
        $name_lower . ' músicas mais tocadas',
        $name_lower . ' músicas mais ouvidas',
    ];

    update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $keywords));
    update_post_meta($post_id, 'rank_math_title', $name . ' %sep% Músicas mais tocadas no Spotify');
    update_post_meta($post_id, 'rank_math_description', 'Veja as músicas mais tocadas de ' . $name . ' no Spotify, com links diretos para ouvir cada faixa.');
    update_post_meta($post_id, 'rank_math_rich_snippet', 'off');

    return $post_id;
}

/**
 * Seed queue
 */
function seed_artist_queue(): void {
    $artists = get_all_ranked_artists();

    update_option(ARTIST_QUEUE_OPTION, $artists);
    update_option(ARTIST_OFFSET_OPTION, 0);
}

/**
 * Validate key
 */
function has_valid_cron_key(): bool {
    if (!defined('CRON_SECRET')) return false;

    $key = $_GET['key'] ?? '';

    return $key && hash_equals(CRON_SECRET, $key);
}

/**
 * Seed endpoint
 */
function handle_seed_request(): void {
    if (!isset($_GET['artist_pages_seed'])) return;

    if (!has_valid_cron_key()) {
        status_header(403);
        exit('Forbidden');
    }

    seed_artist_queue();

    exit('Seed OK');
}
add_action('init', 'handle_seed_request', 999);

/**
 * Process batch
 */
function handle_process_request(): void {
    if (!isset($_GET['artist_pages_process'])) return;

    if (!has_valid_cron_key()) {
        status_header(403);
        exit('Forbidden');
    }

    if (get_transient(ARTIST_LOCK_TRANSIENT)) {
        exit('Locked');
    }

    set_transient(ARTIST_LOCK_TRANSIENT, 1, ARTIST_LOCK_TTL);

    $queue = get_option(ARTIST_QUEUE_OPTION, []);
    $offset = (int) get_option(ARTIST_OFFSET_OPTION, 0);
    $has_changes = false;

    if (!$queue) {
        delete_transient(ARTIST_LOCK_TRANSIENT);
        exit('Empty');
    }

    $parent_id = ensure_artist_parent_page();

    if ($parent_id <= 0) {
        log_artist_pages_error('Artist parent page could not be resolved during batch processing', [
            'offset' => $offset,
            'queue_count' => is_array($queue) ? count($queue) : 0,
        ]);

        delete_transient(ARTIST_LOCK_TRANSIENT);
        exit('Parent page error');
    }

    $batch = array_slice($queue, $offset, ARTIST_BATCH_SIZE);

    foreach ($batch as $artist) {
        try {
            if (!is_array($artist)) continue;

            $id = isset($artist['artist_id']) ? (string) $artist['artist_id'] : '';

            if ($id === '') continue;

            $existing_id = get_existing_artist_page_id($id);

            if ($existing_id > 0) {
                update_post_modified_date($existing_id);
                $has_changes = true;
                continue;
            }

            $created_post_id = create_artist_page($artist, $parent_id);

            if ($created_post_id > 0) {
                $has_changes = true;
            }
        } catch (\Throwable $e) {
            log_artist_pages_error('Unexpected exception during artist page creation', [
                'artist_id' => $artist['artist_id'] ?? '',
                'artist_name' => $artist['artist_name'] ?? '',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    if ($has_changes && class_exists('\RankMath\Sitemap\Cache')) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }

    $offset += count($batch);
    update_option(ARTIST_OFFSET_OPTION, $offset);

    if ($offset >= count($queue)) {
        delete_option(ARTIST_QUEUE_OPTION);
        delete_option(ARTIST_OFFSET_OPTION);
        delete_transient(ARTIST_LOCK_TRANSIENT);
        exit('Done');
    }

    delete_transient(ARTIST_LOCK_TRANSIENT);
    exit("Processed $offset");
}
add_action('init', 'handle_process_request', 999);
