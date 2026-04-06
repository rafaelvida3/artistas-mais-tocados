<?php
/**
 * Plugin Name: Refresh Page Modified Dates
 * Description: Updates the modified date of specific pages weekly by slug.
 * Author: Rafael Vida
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/common/post-updates.php';

/**
 * Target page slugs to update.
 */
const TARGET_PAGE_SLUGS = [
    'artistas-mais-tocados-do-brasil',
    'artistas-de-funk-mais-tocados',
    'artistas-de-sertanejo-mais-tocados',
    'artistas-de-trap-mais-tocados',
    'artistas-de-piseiro-mais-tocados',
    'artistas-de-pagode-mais-tocados',
];

/**
 * Registers weekly interval in WP-Cron.
 */
add_filter('cron_schedules', function (array $schedules): array {
    $schedules['weekly'] = [
        'interval' => WEEK_IN_SECONDS,
        'display'  => __('Once Weekly'),
    ];

    return $schedules;
});

/**
 * Ensures weekly scheduling is set.
 */
add_action('init', function (): void {
    if (!wp_next_scheduled('refresh_pages_event')) {
        wp_schedule_event(time(), 'weekly', 'refresh_pages_event');
    }
});

/**
 * Resolves target page IDs from slugs.
 *
 * @return int[]
 */
function get_target_page_ids_by_slug(): array
{
    $page_ids = [];

    $front_page_id = (int) get_option('page_on_front');

    if ($front_page_id > 0) {
        $page_ids[] = $front_page_id;
    }

    foreach (TARGET_PAGE_SLUGS as $slug) {
        $page = get_page_by_path($slug);

        if (!$page instanceof \WP_Post) {
            error_log('[refresh-pages-dates] Page not found for slug: ' . $slug);
            continue;
        }

        $page_ids[] = (int) $page->ID;
    }

    return array_values(array_unique($page_ids));
}

/**
 * Updates target pages weekly.
 */
add_action('refresh_pages_event', function (): void {
    global $wpdb;

    $page_ids = get_target_page_ids_by_slug();

    if (empty($page_ids)) {
        error_log('[refresh-pages-dates] No target pages found.');
        return;
    }

    $now_local = current_time('mysql');
    $now_gmt   = current_time('mysql', 1);

    foreach ($page_ids as $page_id) {
        $page = get_post($page_id);

        if (!$page instanceof \WP_Post) {
            error_log('[refresh-pages-dates] Invalid page for ID: ' . $page_id);
            continue;
        }

        update_post_modified_date($page_id);
    }

    if (class_exists('\RankMath\Sitemap\Cache')) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }
});