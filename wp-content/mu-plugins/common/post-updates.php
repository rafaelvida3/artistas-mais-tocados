<?php

declare(strict_types=1);

function update_post_modified_date(int $post_id): bool {
    global $wpdb;

    $post = get_post($post_id);

    if (! $post instanceof \WP_Post) {
        error_log('[post-updates] Invalid post for ID: ' . $post_id);

        return false;
    }

    $local = current_time('mysql');
    $utc = current_time('mysql', 1);

    $updated = $wpdb->update(
        $wpdb->posts,
        [
            'post_modified' => $local,
            'post_modified_gmt' => $utc,
        ],
        ['ID' => $post_id],
        ['%s', '%s'],
        ['%d'],
    );

    if ($updated === false) {
        error_log('[post-updates] Failed to update page ID ' . $post_id . ' :: ' . $wpdb->last_error);

        return false;
    }

    clean_post_cache($post_id);

    do_action('save_post', $post_id, $post, true);
    do_action('wp_after_insert_post', $post_id, $post, true);
    do_action('rank_math/sitemap/refresh_post', $post_id);

    return true;
}
