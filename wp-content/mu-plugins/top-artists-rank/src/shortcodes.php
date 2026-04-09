<?php

declare(strict_types=1);

/**
 * @param array<string, string> $atts
 */
function top_artists_shortcode_top_artists(array $atts = []): string {
    if (top_artists_is_editor_context()) {
        return '';
    }

    $script_name = 'top-artists-rank';

    wp_register_script(
        $script_name,
        get_stylesheet_directory_uri() . '/static/js/top-artists-rank.js',
        [],
        '1.0.0',
        true,
    );

    wp_enqueue_script($script_name);

    $genre = isset($atts['genre']) ? sanitize_key((string) $atts['genre']) : 'geral';
    $limit = isset($atts['limit']) ? max(1, (int) $atts['limit']) : 50;
    $rows = top_artists_get_top_artists_by_genre($genre, $limit);

    if ($rows === []) {
        return '<div class="top-artists-empty">Não foi possível carregar o ranking agora.</div>';
    }

    $items = [];

    foreach ($rows as $row) {
        if (! is_array($row)) {
            continue;
        }

        $items[] = [
            'name' => (string) ($row['artist_name'] ?? ''),
            'url' => top_artists_get_artist_destination_url($row),
            'meta' => 'Pop: ' . (string) ($row['popularity'] ?? 0),
            'image_url' => (string) ($row['image_url'] ?? ''),
        ];
    }

    return top_artists_render_artist_list_widget($items, 'top-artists-' . $genre);
}

/**
 * @param array<string, string> $atts
 */
function top_artists_shortcode_top_artist(array $atts = []): string {
    if (top_artists_is_editor_context()) {
        return '';
    }

    $genre = isset($atts['genre']) ? sanitize_key((string) $atts['genre']) : 'geral';
    $rows = top_artists_get_top_artists_by_genre($genre, 1);

    if ($rows === []) {
        return '<div class="top-artists-empty">Não foi possível carregar o ranking agora.</div>';
    }

    $top_artist = $rows[0];
    $artist_bio = top_artists_get_artist_bio_if_available($top_artist);

    return top_artists_render_featured_artist_box(
        $top_artist,
        'Artista mais tocado do momento no ' . ucfirst($genre) . '!',
        $artist_bio,
    );
}

/**
 * @param array<string, string> $atts
 */
function top_artists_shortcode_artist_top_tracks(array $atts = []): string {
    if (top_artists_is_editor_context()) {
        return '';
    }

    $post_id = get_the_ID();

    if (! $post_id) {
        return '';
    }

    $artist_id = isset($atts['artist_id'])
        ? sanitize_text_field((string) $atts['artist_id'])
        : (string) get_post_meta($post_id, 'artist_spotify_id', true);

    $limit = isset($atts['limit']) ? max(1, (int) $atts['limit']) : 10;

    if ($artist_id === '') {
        return '<div class="top-artists-empty">Artista não encontrado.</div>';
    }

    $tracks = top_artists_get_artist_top_tracks($artist_id, $limit);

    if ($tracks === []) {
        return '<div class="top-artists-empty">Não foi possível carregar as músicas agora.</div>';
    }

    $items = [];

    foreach ($tracks as $track) {
        if (! is_array($track)) {
            continue;
        }

        $track_name = isset($track['name']) ? (string) $track['name'] : '';
        $track_url = isset($track['external_urls']['spotify'])
            ? (string) $track['external_urls']['spotify']
            : '';
        $album_name = isset($track['album']['name']) ? (string) $track['album']['name'] : '';

        if ($track_name === '') {
            continue;
        }

        $items[] = [
            'name' => $track_name,
            'url' => $track_url,
            'meta' => $album_name,
            'image_url' => '',
        ];
    }

    return top_artists_render_artist_list_widget($items, 'artist-top-tracks-widget');
}

/**
 * @param array<string, string> $atts
 */
function top_artists_shortcode_artist_featured(array $atts = []): string {
    if (top_artists_is_editor_context()) {
        return '';
    }

    $post_id = get_the_ID();

    if (! $post_id) {
        return '';
    }

    $artist_id = (string) get_post_meta($post_id, 'artist_spotify_id', true);

    if ($artist_id === '') {
        return '<div class="top-artists-empty">Artista não encontrado.</div>';
    }

    $artist = top_artists_get_artist_context_by_spotify_id($artist_id);

    if ($artist === []) {
        return '<div class="top-artists-empty">Não foi possível carregar o artista agora.</div>';
    }

    $artist_name = isset($artist['name']) ? trim((string) $artist['name']) : '';

    if ($artist_name === '') {
        $artist_name = trim((string) get_the_title($post_id));
    }

    $artist['artist_name'] = $artist_name;
    $artist['spotify_url'] = $artist['spotify_url']
        ?? ((string) get_post_meta($post_id, 'artist_spotify_url', true));

    $artist_bio = top_artists_get_artist_bio_if_available($artist);
    $bio_to_display = $artist_bio !== ''
        ? $artist_bio
        : top_artists_get_default_artist_bio_text();

    return top_artists_render_featured_artist_box($artist, '', $bio_to_display);
}

/**
 * @param array<string, string> $atts
 */
function top_artists_shortcode_artist_seo_footer(array $atts = []): string {
    if (top_artists_is_editor_context()) {
        return '';
    }

    $post_id = get_the_ID();

    if (! $post_id) {
        return '';
    }

    $artist_name = get_the_title($post_id);
    $artist_name = is_string($artist_name) ? trim($artist_name) : '';

    if ($artist_name === '') {
        return '';
    }

    ob_start();
    ?>
    <h2 class="wp-block-heading">Músicas mais tocadas de <?php echo esc_html($artist_name); ?></h2>

    <p>
        Nesta página, você acompanha uma seleção atualizada com os principais sucessos
        do artista, reunindo as faixas que mais se destacam entre os ouvintes e que
        seguem em alta nas plataformas digitais.
    </p>

    <p>
        A lista ajuda a entender melhor quais músicas têm maior força no momento, quais
        hits continuam relevantes e quais lançamentos recentes estão ganhando mais
        atenção do público.
    </p>

    <p>
        Para quem busca por <?php echo esc_html($artist_name); ?>, esta página funciona
        como uma forma rápida de descobrir os maiores sucessos e acompanhar a fase atual
        da carreira do artista.
    </p>

    <p>
        Se você gosta de acompanhar rankings musicais e descobrir tendências, vale
        conferir também nossa página de
        <a href="/artistas-mais-tocados-do-brasil">artistas mais tocados do Brasil</a>,
        com uma visão mais ampla dos nomes que estão dominando as reproduções no país.
    </p>

    <p>
        Atualizamos o conteúdo com frequência para manter a seleção de músicas mais
        tocadas de <?php echo esc_html($artist_name); ?> sempre relevante para quem quer
        acompanhar os hits do momento.
    </p>
    <?php

    return (string) ob_get_clean();
}

function top_artists_filter_rank_math_description(string $description): string {
    if (! is_singular('page')) {
        return $description;
    }

    global $post;

    if (! $post instanceof \WP_Post) {
        return $description;
    }

    $artist_id = (string) get_post_meta($post->ID, 'artist_spotify_id', true);

    if ($artist_id === '') {
        return $description;
    }

    $artist_name = get_the_title($post->ID);

    return sprintf(
        'Confira as músicas mais tocadas de %s, seus maiores sucessos e hits mais populares no Spotify.',
        $artist_name,
    );
}

function top_artists_register_shortcodes(): void {
    add_shortcode('top_artists', 'top_artists_shortcode_top_artists');
    add_shortcode('top_artist', 'top_artists_shortcode_top_artist');
    add_shortcode('artist_top_tracks', 'top_artists_shortcode_artist_top_tracks');
    add_shortcode('artist_featured', 'top_artists_shortcode_artist_featured');
    add_shortcode('artist_seo_footer', 'top_artists_shortcode_artist_seo_footer');

    add_filter('rank_math/frontend/description', 'top_artists_filter_rank_math_description');
}

add_action('init', 'top_artists_register_shortcodes');
