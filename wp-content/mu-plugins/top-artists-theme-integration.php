<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_head', function () {
    if (is_admin() || ! is_front_page()) {
        return;
    }
    $site_name = esc_js(get_bloginfo('name'));
    $site_url = esc_url(home_url('/'));
    $logo_url = get_theme_mod('custom_logo');
    $logo_src = esc_url($logo_url ? wp_get_attachment_image_url($logo_url, 'full') : '');

    $genres = ['geral', 'funk', 'sertanejo', 'trap', 'piseiro', 'pagode'];

    ?>
    <!-- BreadcrumbList -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Início",
            "item": "<?= $site_url ?>"
        }
        ]
    }
    </script>
    <!-- ItemLists por gênero -->
    <?php foreach ($genres as $genre) :
        $top_artists = top_artists_get_top_artists_by_genre($genre, 10);
        if (empty($top_artists)) {
            continue;
        }

        // Define título do bloco
        $title = ($genre === 'geral')
            ? 'Top 10 Artistas no Brasil'
            : 'Top 10 Artistas de ' . ucfirst($genre) . ' no Brasil';
        ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "name": "<?= esc_js($title) ?>",
        "itemListOrder": "Descending",
        "itemListElement": [
        <?php
            $count = 1;
        foreach ($top_artists as $artist) {
            $name = esc_js($artist['artist_name'] ?? '');
            $url = esc_url($artist['spotify_url'] ?? '');
            $genre_safe = esc_js($genre ?? '');
            echo '{
                "@type": "ListItem",
                "position": ' . $count . ',
                "item": {
                "@type": "MusicGroup",
                "name": "' . $name . '",
                "genre": "' . $genre_safe . '",
                "url": "' . $url . '"
                }
            }';
            if ($count < count($top_artists)) {
                echo ',';
            }
            $count++;
        }
        ?>
        ]
    }
    </script>
    <?php endforeach;
});
