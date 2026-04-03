<?php
/**
 * Plugin Name: IndexNow URL List
 * Description: Adds an admin page that lists all public URLs from the site for IndexNow submission.
 * Version: 1.0.0
 * Author: Rafael
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register the admin page under Tools.
 *
 * @return void
 */
function indexnow_url_list_register_admin_menu(): void {
	add_management_page(
		'IndexNow URLs',
		'IndexNow URLs',
		'manage_options',
		'indexnow-url-list',
		'indexnow_url_list_render_admin_page'
	);
}

add_action('admin_menu', 'indexnow_url_list_register_admin_menu');

/**
 * Get all public URLs from public post types.
 *
 * @return array<int, string>
 */
function indexnow_url_list_get_all_urls(): array {

    $post_ids = get_posts([
    	'post_type' => 'page',
    	'post_status' => 'publish',
    	'posts_per_page' => -1,
    	'fields' => 'ids'
    ]);
    
	if (!is_array($post_ids) || empty($post_ids)) {
		return [];
	}

	$urls = [];

	foreach ($post_ids as $post_id) {
		$permalink = get_permalink((int) $post_id);

		if (is_string($permalink) && $permalink !== '') {
			$urls[] = $permalink;
		}
	}

	$urls = array_values(array_unique($urls));

	return $urls;
}

/**
 * Render the admin page.
 *
 * @return void
 */
function indexnow_url_list_render_admin_page(): void {
	if (!current_user_can('manage_options')) {
		wp_die('You do not have permission to access this page.');
	}

	$urls = indexnow_url_list_get_all_urls();
	$plain_output = implode("\n", $urls);
	$count = count($urls);

	?>
	<div class="wrap">
		<h1>IndexNow URLs</h1>

		<p>Total de URLs encontradas: <strong><?php echo esc_html((string) $count); ?></strong></p>

		<p>
			Use a lista abaixo para copiar e enviar para a API do IndexNow via Rank Math.
		</p>

		<p>
			<a href="<?php echo esc_url(admin_url('tools.php?page=indexnow-url-list&format=plain')); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">
				Abrir saída bruta
			</a>
		</p>

		<textarea readonly style="width: 100%; min-height: 500px; font-family: monospace;"><?php echo esc_textarea($plain_output); ?></textarea>
	</div>
	<?php
}

/**
 * Output raw plain text list when requested.
 *
 * @return void
 */
function indexnow_url_list_maybe_output_plain_text(): void {
	if (!is_admin()) {
		return;
	}

	if (!current_user_can('manage_options')) {
		return;
	}

	$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
	$format = isset($_GET['format']) ? sanitize_text_field(wp_unslash($_GET['format'])) : '';

	if ($page !== 'indexnow-url-list' || $format !== 'plain') {
		return;
	}

	$urls = indexnow_url_list_get_all_urls();

	nocache_headers();
	header('Content-Type: text/plain; charset=utf-8');

	echo implode("\n", $urls);
	exit;
}

add_action('admin_init', 'indexnow_url_list_maybe_output_plain_text');