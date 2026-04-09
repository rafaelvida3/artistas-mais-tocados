<?php

declare(strict_types=1);

function top_artists_render_featured_artist_button(string $url, string $label): string {
    if ($url === '') {
        return '';
    }

    ob_start();
    ?>
    <div class="wp-block-buttons" style="margin-top:0;margin-bottom:0">
        <div class="wp-block-button has-custom-width wp-block-button__width-100 is-style-fill">
            <a
                class="wp-block-button__link has-text-align-center wp-element-button"
                href="<?php echo esc_url($url); ?>"
                target="_blank"
                rel="nofollow noopener"
            >
                <i class="fa-solid fa-arrow-right"></i><?php echo esc_html($label); ?>
            </a>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function top_artists_render_featured_artist_box(
    array $artist,
    string $description,
    string $artist_bio = '',
): string {
    $artist_name = isset($artist['artist_name']) ? (string) $artist['artist_name'] : '';
    $artist_image_url = isset($artist['image_url']) ? (string) $artist['image_url'] : '';
    $artist_spotify_url = isset($artist['spotify_url']) ? (string) $artist['spotify_url'] : '';

    if ($artist_name === '') {
        return '<div class="top-artists-empty">Artista não encontrado.</div>';
    }

    ob_start();
    ?>
    <div class="featured-artist">
        <?php if ($artist_image_url !== '') : ?>
            <img
                src="<?php echo esc_url($artist_image_url); ?>"
                class="artist-image"
                alt="<?php echo esc_attr($artist_name); ?>"
                loading="lazy"
                width="450"
            >
        <?php endif; ?>

        <h3><?php echo esc_html($artist_name); ?></h3>

        <?php if ($description !== '') : ?>
            <p><?php echo esc_html($description); ?></p>
        <?php endif; ?>

        <?php if ($artist_bio !== '') : ?>
            <div class="artist-bio-block">
                <p><?php echo wp_kses_post(nl2br(esc_html($artist_bio))); ?></p>
            </div>
        <?php endif; ?>

        <?php
        echo top_artists_render_featured_artist_button(
            $artist_spotify_url,
            'Ouvir no Spotify',
        );
    ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function top_artists_render_artist_list_widget(
    array $items,
    string $wrapper_class = '',
): string {
    if ($items === []) {
        return '<div class="top-artists-empty">Não foi possível carregar os dados agora.</div>';
    }

    $wrapper_classes = trim('top-artists-widget ' . $wrapper_class);

    ob_start();
    ?>
    <div class="<?php echo esc_attr($wrapper_classes); ?>">
        <ol class="top-artists-list">
            <?php foreach ($items as $index => $item) : ?>
                <?php
                if (! is_array($item)) {
                    continue;
                }

                $name = isset($item['name']) ? (string) $item['name'] : '';
                $url = isset($item['url']) ? (string) $item['url'] : '';
                $meta = isset($item['meta']) ? (string) $item['meta'] : '';
                $image_url = isset($item['image_url']) ? (string) $item['image_url'] : '';
                $is_external = ! empty($item['is_external']);

                if ($name === '') {
                    continue;
                }
                ?>
                <li class="top-artist-item">
                    <span class="top-artist-rank"><?php echo (string) ($index + 1); ?></span>

                    <?php if ($image_url !== '') : ?>
                        <img
                            class="top-artist-image"
                            src="<?php echo esc_url($image_url); ?>"
                            alt="<?php echo esc_attr($name); ?>"
                            width="36"
                            height="36"
                            loading="lazy"
                        >
                    <?php endif; ?>

                    <div class="top-artist-info">
                        <?php if ($url !== '') : ?>
                            <a
                                class="top-artist-link"
                                href="<?php echo esc_url($url); ?>"
                                <?php echo $is_external ? 'target="_blank" rel="nofollow noopener"' : ''; ?>
                            >
                                <?php echo esc_html($name); ?>
                            </a>
                        <?php else : ?>
                            <span class="top-artist-link"><?php echo esc_html($name); ?></span>
                        <?php endif; ?>

                        <?php if ($meta !== '') : ?>
                            <small class="top-artist-pop"><?php echo esc_html($meta); ?></small>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php

    return (string) ob_get_clean();
}
