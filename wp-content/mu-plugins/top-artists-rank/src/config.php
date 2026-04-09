<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/project_config.php';

if (! defined('TOP_ARTISTS_REQUEST_TIMEOUT')) {
    define('TOP_ARTISTS_REQUEST_TIMEOUT', 10);
}

if (! defined('TOP_ARTISTS_API_BASE_URL')) {
    define('TOP_ARTISTS_API_BASE_URL', 'https://api.spotify.com/v1');
}

if (! defined('TOP_ARTISTS_MARKET')) {
    define('TOP_ARTISTS_MARKET', 'BR');
}

function top_artists_is_editor_context(): bool {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return true;
    }

    if (is_admin() && ! wp_doing_ajax()) {
        return true;
    }

    if (function_exists('wp_is_json_request') && wp_is_json_request()) {
        return true;
    }

    return isset($_GET['context']) && $_GET['context'] === 'edit';
}
