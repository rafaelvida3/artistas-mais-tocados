<?php

/**
 * Plugin Name: Refresh Page Modified Dates
 * Description: Updates the modified date of specific pages weekly by slug.
 * Author: Rafael Vida
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/common/post-updates.php';
require_once __DIR__ . '/top-artists-rank/src/project_config.php';

add_filter('cron_schedules', 'refresh_pages_register_weekly_interval');

function refresh_pages_register_weekly_interval(array $schedules): array {
    $schedules['weekly'] = [
        'interval' => WEEK_IN_SECONDS,
        'display' => __('Once Weekly'),
    ];

    return $schedules;
}

add_action('init', 'refresh_pages_schedule_event');

function refresh_pages_schedule_event(): void {
    // WP-Cron is used only for lightweight internal scheduling.
    // External cron remains the primary execution trigger.
    if (! wp_next_scheduled('refresh_pages_event')) {
        wp_schedule_event(time(), 'weekly', 'refresh_pages_event');
    }
}

/**
 * @return int[]
 */
function refresh_pages_get_target_page_ids_by_slug(): array {
    $page_ids = [];
    $front_page_id = (int) get_option('page_on_front');

    if ($front_page_id > 0) {
        $page_ids[] = $front_page_id;
    }

    foreach (top_artists_get_refresh_target_slugs() as $slug) {
        $page = get_page_by_path($slug);

        if (! $page instanceof \WP_Post) {
            error_log('[refresh-pages-dates] Page not found for slug: ' . $slug);
            continue;
        }

        $page_ids[] = (int) $page->ID;
    }

    return array_values(array_unique($page_ids));
}

add_action('refresh_pages_event', 'refresh_pages_update_target_pages');

function refresh_pages_update_target_pages(): void {
    $page_ids = refresh_pages_get_target_page_ids_by_slug();

    if ($page_ids === []) {
        error_log('[refresh-pages-dates] No target pages found.');

        return;
    }

    foreach ($page_ids as $page_id) {
        $page = get_post($page_id);

        if (! $page instanceof \WP_Post) {
            error_log('[refresh-pages-dates] Invalid page for ID: ' . $page_id);
            continue;
        }

        top_artists_update_post_modified_date($page_id);
    }

    if (class_exists("\RankMath\Sitemap\Cache")) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }
}
