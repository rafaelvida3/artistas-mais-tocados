<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/src/ranking_helpers.php';

use WpOrg\Requests\Requests;
use WpOrg\Requests\Response;
use WpOrg\Requests\Exception as RequestsException;

define('TIMEOUT', 10);
define('API_BASE_URL', 'https://api.spotify.com/v1');
define('MARKET', 'BR');

function is_editor_context(): bool {
    if (defined('REST_REQUEST') && REST_REQUEST) return true;
    if (is_admin() && !wp_doing_ajax()) return true;
    if (function_exists('wp_is_json_request') && wp_is_json_request()) return true;
    if (isset($_GET['context']) && $_GET['context'] === 'edit') return true;
    return false;
}

/**
 * Retrieves Spotify access token (Client Credentials flow) and caches it.
 */
function get_spotify_token(): string {
    // Try transient first
    $cached = get_transient('spotify_access_token');
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    // Env/const guards
    $client_id     = defined('SPOTIFY_CLIENT_ID') ? (string) SPOTIFY_CLIENT_ID : '';
    $client_secret = defined('SPOTIFY_CLIENT_SECRET') ? (string) SPOTIFY_CLIENT_SECRET : '';
    if ($client_id === '' || $client_secret === '') {
        return '';
    }

    // Build request to Accounts API using our batched JSON helper
    $url = 'https://accounts.spotify.com/api/token';

    /** @var array<string, array<string, mixed>> $requests */
    $requests = [
        'token' => [
            'url'     => $url,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            // Using Requests::POST to match OAuth token grant
            'type'    => Requests::POST,
            // NOTE: Requests accepts 'data' for POST body
            'data'    => [
                'grant_type' => 'client_credentials',
            ],
            'options' => [
                'timeout'     => TIMEOUT,
                'redirection' => 2,
            ],
        ],
    ];

    // Success parser: validate JSON, cache, and return the token string
    $on_success = static function (string $key, array $json, array $req): string {
        // Defensive JSON checks
        $token = isset($json['access_token']) ? (string) $json['access_token'] : '';
        if ($token === '') {
            // Let the caller fall back to default ('') if access_token is missing
            return '';
        }

        // Cache with small safety margin
        $expires = isset($json['expires_in']) && is_numeric($json['expires_in'])
            ? (int) $json['expires_in']
            : 3600;

        // Use max(300, expires - 60) to avoid thrashing and refresh slightly early
        set_transient('spotify_access_token', $token, max(300, $expires - 60));
        return $token;
    };

    // Default factory for failures
    $make_default = static function (string $key): string {
        return '';
    };

    /** @var array{token?: string} $result */
    $result = request_multiple_json($requests, $on_success, $make_default);

    return isset($result['token']) && is_string($result['token']) ? $result['token'] : '';
}

/**
 * Fire parallel HTTP requests, handle network/status/json errors uniformly,
 * and delegate success parsing to a callback.
 *
 * @param array<string, array{url:string, headers:array, type:string, options:array}> $requests
 * @param callable(string, array, array): mixed $on_success   // ($key, $json, $req) => parsed value
 * @param callable(string): mixed              $make_default  // ($key) => default value for failures
 * @return array<string, mixed>                                // map: key => parsed/default
 */
function request_multiple_json(
    array $requests,
    callable $on_success,
    callable $make_default
): array {
    /** @var array<string, mixed> $result */
    $result = [];

    if (!$requests) {
        return $result;
    }

    $responses = Requests::request_multiple($requests);

    foreach ($responses as $key => $res) {
        $url = $requests[$key]['url'] ?? '';
        $default_value = $make_default($key);

        // Network / transport-level error
        if ($res instanceof RequestsException) {
            error_log(sprintf('[top-artists] API ERROR %s :: %s', $url, $res->getMessage()));
            $result[$key] = $default_value;
            continue;
        }

        if ($res instanceof Response) {
            $code = (int) $res->status_code;

            if ($code !== 200) {
                // If 429, try to log Retry-After for observability
                $retry_after = $res->headers['retry-after'] ?? null;
                $extra = $retry_after ? ' :: RETRY-AFTER ' . $retry_after : '';
                error_log(sprintf('[top-artists] API ERROR %s :: CODE %d%s', $url, $code, $extra));
                $result[$key] = $default_value;
                continue;
            }

            $json = json_decode($res->body, true);

            if (!is_array($json)) {
                error_log(sprintf('[top-artists] API ERROR %s :: invalid JSON', $url));
                $result[$key] = $default_value;
                continue;
            }

            // Success path — delegate parsing/caching to the callback
            try {
                $parsed = $on_success($key, $json, $requests[$key]);
                $result[$key] = $parsed;
            } catch (\Throwable $e) {
                error_log(sprintf('[top-artists] PARSE ERROR key=%s :: %s', (string) $key, $e->getMessage()));
                $result[$key] = $default_value;
            }

            continue;
        }

        // Unexpected response type
        error_log(sprintf('[top-artists] API ERROR %s :: unexpected response type', $url));
        $result[$key] = $default_value;
    }

    return $result;
}

/**
 * Curated Spotify playlist IDs by genre.
 * Edit this array whenever you want to change sources.
 *
 * @return array<string, array<string>>
 */
function get_curated_playlists_map(): array {
    $map = [
        'funk'      => [
            '3tXQEdXHBCnHHfjGgbNFQ0',
            '1cfmJq1vlOvxkrPRwSl873'
        ],
        'trap'      => [
            '3rPugprs5WtLcNH1VqXJDx',
            '3QfUDYpxCgQM77RY7WK890'
        ],
        // 'sertanejo' => [
        //     '7110oQdXKCBofaBanEYo1Z',
        //     '3xwHGwphocKJ7vayoFSqCS'
        // ],
        'sertanejo' => [
            '3rPugprs5WtLcNH1VqXJDx',
            '1KZkxSv0kMXOGYS4lLl2GO'
        ],
        'piseiro' => [
            '3xwHGwphocKJ7vayoFSqCS',
            '7pJUXntr3BMLFFQx4Whkwh'
        ],
        'pagode' => [
            '05341M5VjVdmig3M7NGTOd',
            '7g7vsEvcNEdRDGNuBL4SmY'
        ],
        'geral'     => [
            '2UgUYCjD5nuBGyWRaWBoUf',
            '27KCnBs5sZTXgaF4WIy5XQ',
            '1msY3c9fzgE3V11aNhsq2O',
            '4sOqZUOconQiyH55o3Kz7x'
        ],
    ];

    return $map;
}

/**
 * Get curated playlist IDs for a given genre, capped to $max_playlists if provided.
 *
 * @param string $genre
 * @param int    $max_playlists
 * @return array<string>
 */
function get_playlists_for_genre(string $genre): array {
    $map = get_curated_playlists_map();
    $list = $map[$genre] ?? [];
    if (!is_array($list) || !$list) { return []; }
    
    return $list;
}

/**
 * Parallel: artist -> top tracks
 *
 * - Caches ONLY API results in "artist_tracks_{artist_id}" for 12h.
 * - Returns map: artist_id => tracks[]|null
 *
 * @param string        $token
 * @param array<string> $artist_ids
 * @return array<string, ?array>  // tracks array or null on failure
 */
function fetch_artist_top_tracks_parallel(string $token, array $artist_ids): array {
    $headers   = [
        'Authorization' => 'Bearer ' . $token,
        'Accept'        => 'application/json',
    ];
    /** @var array<string, array{url:string, headers:array, type:string, options:array}> $requests */
    $requests  = [];
    /** @var array<string, ?array> $prefilled */
    $prefilled = [];

    // Deduplicate and skip empties; prefill from cache
    foreach (array_unique(array_filter(array_map('strval', $artist_ids))) as $aid) {
        $ck = "artist_top_tracks_{$aid}";
        $cached = get_transient($ck);
        if (is_array($cached)) {
            $prefilled[$aid] = $cached;
            continue;
        }

        $url = API_BASE_URL . "/artists/{$aid}/top-tracks?market=" . MARKET;

        $requests[$aid] = [
            'url'     => $url,
            'headers' => $headers,
            'type'    => Requests::GET,
            'options' => ['timeout' => TIMEOUT],
        ];
    }

    // Everything came from cache
    if (!$requests) {
        return $prefilled;
    }

    // Success parser: store tracks[] (or null) and cache when valid array
    $on_success = static function (string $aid, array $json) {
        $tracks = $json['tracks'] ?? null;
        $parsed = is_array($tracks) ? $tracks : null;
        if (is_array($parsed)) {
            set_transient("artist_top_tracks_{$aid}", $parsed, 12 * HOUR_IN_SECONDS);
        }
        return $parsed;
    };

    // Default value on failure
    $make_default = static function (string $aid) {
        return null;
    };

    $fetched = request_multiple_json($requests, $on_success, $make_default);
    return $prefilled + $fetched;
}

/**
 * Parallel: artist -> albums
 *
 * @param string        $token
 * @param array<string> $artist_ids
 * @return array<string, ?array> Map: artist_id => albums[]|null
 */
function fetch_albums_parallel(string $token, array $artist_ids): array
{
    $headers  = [
        'Authorization' => 'Bearer ' . $token,
        'Accept'        => 'application/json',
    ];
    $requests = [];
    $prefilled = [];

    foreach (array_unique($artist_ids) as $aid) {
        $cache_key = "artist_albums_{$aid}";
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $prefilled[$aid] = $cached;
            continue;
        }

        $url = API_BASE_URL . "/artists/{$aid}/albums?include_groups=album,single,appears_on&market=" . MARKET . "&limit=50";
        $requests[$aid] = [
            'url'     => $url,
            'headers' => $headers,
            'type'    => Requests::GET,
            'options' => ['timeout' => TIMEOUT],
        ];
    }

    if (!$requests) {
        return $prefilled;
    }

    $on_success = static function (string $aid, array $json) {
        $items = $json['items'] ?? null;
        $parsed = is_array($items) ? $items : null;
        if (is_array($parsed)) {
            set_transient("artist_albums_{$aid}", $parsed, 12 * HOUR_IN_SECONDS);
        }
        return $parsed;
    };

    $make_default = static function (string $aid) {
        return null;
    };

    $fetched = request_multiple_json($requests, $on_success, $make_default);
    return $prefilled + $fetched;
}

/**
 * Parallel: playlist -> tracks + latest_added_ts (+ optional BR ratio computed on the fly, no transient)
 *
 * - Caches ONLY API results (playlist tracks) in "playlist_tracks_{pid}_{limit}".
 *
 * @param string        $token
 * @param array<string> $playlist_ids
 * @param int           $limit
 */
function fetch_playlist_tracks_parallel(
    string $token,
    array $playlist_ids,
    int $limit = 100
): array {
    $headers   = [
        'Authorization' => 'Bearer ' . $token,
        'Accept'        => 'application/json',
    ];
    $requests  = [];
    $prefilled = [];

    // 2) Prefill base cache (tracks + latest)
    foreach ($playlist_ids as $pid) {
        $ck = "playlist_tracks_{$pid}_{$limit}";
        $base_cached = get_transient($ck);

        if (is_array($base_cached)) {
            $prefilled[$pid] = $base_cached;
            continue;
        }

        $url = add_query_arg([
            'limit'  => $limit,
            'fields' => 'items(added_at,track(artists(id,name),id,name,album(release_date,release_date_precision))),next',
            'market' => MARKET,
        ], API_BASE_URL . "/playlists/{$pid}/tracks");

        $requests[$pid] = [
            'url'     => $url,
            'headers' => $headers,
            'type'    => Requests::GET,
            'options' => ['timeout' => TIMEOUT],
        ];
    }

    // 3) Se tudo veio do cache base
    if (!$requests) {
        return $prefilled;
    }

    // 4) Success parser: monta base (tracks + latest) e cacheia
    $on_success = static function (string $pid, array $json) use ($limit) {
        $items  = $json['items'] ?? [];

        $tracks = [];

        if (is_array($items)) {
            foreach ($items as $row) {
                if (!is_array($row)) { continue; }
                if (!empty($row['track'])) {
                    $tracks[] = $row['track'];
                }
            }
        }
        
        if (empty($tracks)) {
            error_log(sprintf('[top-artists] PLAYLIST EMPTY :: %s', $pid));
        }

        set_transient("playlist_tracks_{$pid}_{$limit}", $tracks, HOUR_IN_SECONDS);
        return $tracks;
    };

    $make_default = static function (string $pid) {
        
    };

    // 5) Busca em paralelo a base
    $fetched = request_multiple_json($requests, $on_success, $make_default);
    $packs   = $prefilled + $fetched;

    return $packs;
}

function fetch_artists_parallel(string $token, array $artist_ids): array {
    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Accept'        => 'application/json',
    ];

    // 1) Dedup/limpeza e prefill do cache
    $unique_ids = [];
    foreach ($artist_ids as $raw_id) {
        $id = (string) $raw_id;
        if ($id !== '' && !in_array($id, $unique_ids, true)) {
            $unique_ids[] = $id;
        }
    }

    /** @var array<string, array>|array<string, null> $prefilled */
    $prefilled = [];
    $to_fetch  = [];

    foreach ($unique_ids as $aid) {
        $cached = get_transient("artist_{$aid}");
        if (is_array($cached)) {
            // ✅ mantenha consistência: cache guarda PARSED (não o bruto)
            $prefilled[$aid] = $cached;
        } else {
            $to_fetch[] = $aid;
        }
    }

    if (!$to_fetch) {
        return $prefilled;
    }

    // 2) Monta batches de até 50 IDs
    /** @var array<string, array{url:string, headers:array, type:string, options:array}> $requests */
    $requests = [];
    $chunks   = array_chunk($to_fetch, 50);
    foreach ($chunks as $i => $chunk) {
        $key = 'batch_' . $i;
        $url = add_query_arg(['ids' => implode(',', $chunk)], API_BASE_URL . '/artists');
        $requests[$key] = [
            'url'     => $url,
            'headers' => $headers,
            'type'    => Requests::GET,
            'options' => ['timeout' => TIMEOUT],
        ];
    }

    /**
     * 3) Parser de sucesso
     * Retorna um MAPA por batch: [artist_id => parsed_artist]
     * O caller depois achata isso num único array.
     *
     * @return array<string, array>  // mapa id => parsed
     */
    $on_success = static function (string $batch_key, array $json): array {
        $out = [];
        $artists = $json['artists'] ?? [];
        if (!is_array($artists)) {
            return $out;
        }

        foreach ($artists as $a) {
            if (!is_array($a)) { continue; }
            $id = isset($a['id']) && is_string($a['id']) ? $a['id'] : '';
            if ($id === '') { continue; }
            if ($a['type'] !== 'artist') {continue; }

            $name       = (string) ($a['name'] ?? '');
            $popularity = (int)    ($a['popularity'] ?? 0);
            $images     = is_array($a['images'] ?? null) ? $a['images'] : [];
            $image_url  = isset($images[0]['url']) ? (string) $images[0]['url'] : '';

            $parsed = [
                'popularity'  => $popularity,
                'name'        => $name,
                'image_url'   => $image_url,
                'spotify_url' => 'https://open.spotify.com/artist/' . $id,
                'genres'      => is_array($a['genres'] ?? null) ? $a['genres'] : [],
            ];

            // cache PARSED por 12h (consistente com as outras)
            set_transient("artist_{$id}", $parsed, 12 * HOUR_IN_SECONDS);
            $out[$id] = $parsed;
        }

        return $out;
    };

    // 4) Valor padrão: mapa vazio para o batch
    $make_default = static function (string $batch_key): array {
        return [];
    };

    // 5) Busca e achata o resultado dos batches
    $fetched_batches = request_multiple_json($requests, $on_success, $make_default);
    /** @var array<string, array> $fetched_flat */
    $fetched_flat = [];
    foreach ($fetched_batches as $batch_map) {
        if (is_array($batch_map)) {
            foreach ($batch_map as $aid => $parsed) {
                if (is_string($aid) && is_array($parsed)) {
                    $fetched_flat[$aid] = $parsed;
                }
            }
        }
    }

    // 6) Merge com o que já veio do cache
    return $prefilled + $fetched_flat;
}

/**
 * Ranking por gênero (Spotify popularity) com filtros de recência e coerência de gênero/BR.
 *
 * @param string $genre   Ex.: 'geral','funk','sertanejo','trap','piseiro','pagode'
 * @param int    $limit   Número de artistas no ranking
 * @param bool   $br_only Mantém só artistas BR pelo heurístico de gêneros
 * @return array<int, array{artist_id:string, artist_name:string, popularity:int, image_url:string, spotify_url:string}>
 */
function get_top_artists_by_genre(string $genre, int $limit = 50): array {
    $limit = max(1, $limit);

    $token = get_spotify_token();
    if ($token === '') {
        return [];
    }
    
    $playlist_ids = get_playlists_for_genre($genre);

    $artist_map = []; // [artist_id => artist_name]
    
    $playlist_tracks = fetch_playlist_tracks_parallel($token, $playlist_ids, 50);

    foreach ($playlist_tracks as $pid => $tracks) {
        if (!is_array ($tracks)) {
            continue;
        }
        
        foreach ($tracks as $t) {
            foreach ($t['artists'] as $art) {
                if (!isset($art['id'], $art['name'])) { continue; }

                if (artist_is_denied_for_genre($art['id'], $genre)) { continue; }

                $artist_map[(string)$art['id']] = (string)$art['name'];
            }
        }
    }

    unset($playlist_tracks);
    
    if (!$artist_map) {
        return [];
    }

    $artist_ids = array_keys($artist_map);    
    $info_map = fetch_artists_parallel($token, $artist_ids);

    // Brazilian heuristic filter
    $rows = [];
    foreach ($artist_map as $aid => $fallback_name) {

        $info = $info_map[$aid] ?? [
            'popularity'  => 0,
            'name'        => $fallback_name,
            'image_url'   => '',
            'spotify_url' => 'https://open.spotify.com/artist/' . $aid,
            'genres'      => [],
        ];

        $artist_genres = is_array($info['genres'] ?? null) ? $info['genres'] : [];

        if (!artist_matches_genre($genre, $artist_genres)) { continue; }

        $rows[] = [
            'artist_id'   => $aid,
            'artist_name' => (string) ($info['name'] ?: $fallback_name),
            'popularity'  => (int) $info['popularity'],
            'image_url'   => (string) $info['image_url'],
            'spotify_url' => (string) $info['spotify_url'],
        ];
    }

    unset($info_map, $top_tracks, $albums);

    if (!$rows) {
        // evite cachear vazio por 1h; retorna vazio direto
        return [];
    }

    // Sort by popularity description; tiebreaker by name.
    usort($rows, static function(array $a, array $b): int {
        $cmp = $b['popularity'] <=> $a['popularity'];
        return $cmp !== 0 ? $cmp : strcmp((string)$a['artist_name'], (string)$b['artist_name']);
    });

    $rows = array_slice($rows, 0, $limit);

    return $rows;
}

function get_artist_context_by_spotify_id(string $artist_id): array {
    if ($artist_id === '') {
        return [];
    }

    $token = get_spotify_token();

    if ($token === '') {
        return [];
    }

    $artists_map = fetch_artists_parallel($token, [$artist_id]);
    $artist = $artists_map[$artist_id] ?? [];

    return is_array($artist) ? $artist : [];
}

function get_artist_top_tracks(string $artist_id, int $limit = 10): array {
    if ($artist_id === '') {
        return [];
    }

    $token = get_spotify_token();

    if ($token === '') {
        return [];
    }

    $tracks_map = fetch_artist_top_tracks_parallel($token, [$artist_id]);
    $tracks = $tracks_map[$artist_id] ?? [];

    if (!is_array($tracks)) {
        return [];
    }

    return array_slice($tracks, 0, $limit);
}

function render_featured_artist_button(string $url, string $label): string {
    if ($url === '') {
        return '';
    }

    ob_start(); ?>
    <div class="wp-block-buttons" style="margin-top:0;margin-bottom:0">
        <div class="wp-block-button has-custom-width wp-block-button__width-100 is-style-fill">
            <a class="wp-block-button__link has-text-align-center wp-element-button" href="<?php echo esc_url($url); ?>" target="_blank" rel="nofollow noopener">
                <i class="fa-solid fa-arrow-right"></i><?php echo esc_html($label); ?>
            </a>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function render_featured_artist_box(array $artist, string $description, string $artist_bio = ''): string {
    $artist_name = isset($artist['artist_name']) ? (string) $artist['artist_name'] : '';
    $artist_image_url = isset($artist['image_url']) ? (string) $artist['image_url'] : '';
    $artist_spotify_url = isset($artist['spotify_url']) ? (string) $artist['spotify_url'] : '';

    if ($artist_name === '') {
        return '<div class="top-artists-empty">Artista não encontrado.</div>';
    }

    ob_start(); ?>
    <div class="featured-artist">
        <?php if ($artist_image_url !== ''): ?>
            <img
                src="<?php echo esc_url($artist_image_url); ?>"
                class="artist-image"
                alt="<?php echo esc_attr($artist_name); ?>"
                loading="lazy"
                width="450"
            >
        <?php endif; ?>

        <h3><?php echo esc_html($artist_name); ?></h3>
        <?php if ($description !== ''): ?>
            <p><?php echo esc_html($description); ?></p>
        <?php endif; ?>

        <?php if ($artist_bio !== ''): ?>
            <div class="artist-bio-block">
                <p><?php echo wp_kses_post(nl2br(esc_html($artist_bio))); ?></p>
            </div>
        <?php endif; ?>

        <?php echo render_featured_artist_button($artist_spotify_url, 'Ouvir no Spotify'); ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function get_artist_page_url_by_spotify_id(string $artist_id): string {
    if ($artist_id === '') {
        return '';
    }

    $page_id = get_existing_artist_page_id($artist_id);

    if ($page_id <= 0) {
        return '';
    }

    $url = get_permalink($page_id);

    return is_string($url) ? $url : '';
}

function get_artist_destination_url(array $artist): string {
    $artist_id = isset($artist['artist_id']) ? (string) $artist['artist_id'] : '';

    if ($artist_id !== '') {
        $internal_url = get_artist_page_url_by_spotify_id($artist_id);

        if ($internal_url !== '') {
            return $internal_url;
        }
    }

    $spotify_url = isset($artist['spotify_url']) ? (string) $artist['spotify_url'] : '';

    return $spotify_url;
}

function render_artist_list_widget(array $items, string $wrapper_class = ''): string {
    if (!$items) {
        return '<div class="top-artists-empty">Não foi possível carregar os dados agora.</div>';
    }

    $wrapper_classes = trim('top-artists-widget ' . $wrapper_class);

    ob_start(); ?>
    <div class="<?php echo esc_attr($wrapper_classes); ?>">
        <ol class="top-artists-list">
            <?php foreach ($items as $index => $item): ?>
                <?php
                if (!is_array($item)) {
                    continue;
                }

                $name = isset($item['name']) ? (string) $item['name'] : '';
                $url = isset($item['url']) ? (string) $item['url'] : '';
                $meta = isset($item['meta']) ? (string) $item['meta'] : '';
                $image_url = isset($item['image_url']) ? (string) $item['image_url'] : '';
                $is_external = !empty($item['is_external']);

                if ($name === '') {
                    continue;
                }
                ?>
                <li class="top-artist-item">
                    <span class="top-artist-rank"><?php echo (string) ($index + 1); ?></span>

                    <?php if ($image_url !== ''): ?>
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
                        <?php if ($url !== ''): ?>
                            <a
                                class="top-artist-link"
                                href="<?php echo esc_url($url); ?>"
                                <?php echo $is_external ? 'target="_blank" rel="nofollow noopener"' : ''; ?>
                            >
                                <?php echo esc_html($name); ?>
                            </a>
                        <?php else: ?>
                            <span class="top-artist-link"><?php echo esc_html($name); ?></span>
                        <?php endif; ?>

                        <?php if ($meta !== ''): ?>
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

function sanitize_artist_bio(string $bio): string {
    $bio = html_entity_decode($bio, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $bio = wp_strip_all_tags($bio);

    $bio = preg_replace('/Read more on Last\.fm.*$/i', '', $bio);
    $bio = preg_replace('/User-contributed text.*$/i', '', $bio);

    $bio = preg_replace('/[ \t]+/', ' ', (string) $bio);

    // normaliza quebra de linha
    $bio = preg_replace("/\r\n|\r/", "\n", (string) $bio);

    // 🔥 DUPLICA quebras simples
    $bio = preg_replace("/(?<!\n)\n(?!\n)/", "\n\n", (string) $bio);

    // evita excesso
    $bio = preg_replace("/\n{3,}/", "\n\n", (string) $bio);

    return trim((string) $bio);
}

function get_artist_bio_cache_key(string $artist_name): string {
    $artist_name = strtolower(trim($artist_name));

    return 'artist_bio_' . md5($artist_name);
}

function get_default_artist_bio_text(): string {
    return 'Confira as músicas mais tocadas e os maiores sucessos deste artista.';
}

function get_artist_bio_if_available(array $artist): string {
    $artist_name = isset($artist['artist_name']) ? trim((string) $artist['artist_name']) : '';

    if ($artist_name === '') {
        $artist_name = isset($artist['name']) ? trim((string) $artist['name']) : '';
    }

    if ($artist_name === '') {
        return '';
    }

    return get_artist_bio_by_name($artist_name);
}

function request_artist_bio_from_lastfm(string $artist_name, string $lang = 'pt'): string {
    if ($artist_name === '') {
        return '';
    }

    if (!defined('LASTFM_API_KEY') || (string) LASTFM_API_KEY === '') {
        error_log('[LastFM] API key not defined');
        return '';
    }

    $query_args = [
        'method' => 'artist.getinfo',
        'artist' => $artist_name,
        'api_key' => (string) LASTFM_API_KEY,
        'format' => 'json',
    ];

    if ($lang !== '') {
        $query_args['lang'] = $lang;
    }

    $url = add_query_arg($query_args, 'https://ws.audioscrobbler.com/2.0/');

    $response = wp_remote_get($url, [
        'timeout' => TIMEOUT,
    ]);

    if (is_wp_error($response)) {
        error_log('[LastFM] WP_Error: ' . $response->get_error_message());
        return '';
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
        error_log('[LastFM] HTTP error: ' . $status_code . ' | Artist: ' . $artist_name);
        return '';
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (!is_array($json)) {
        error_log('[LastFM] Invalid JSON response | Artist: ' . $artist_name);
        return '';
    }

    if (!isset($json['artist'])) {
        error_log('[LastFM] Missing artist key in response | Artist: ' . $artist_name);
        return '';
    }

    $bio = '';

    if (isset($json['artist']['bio']['content']) && is_string($json['artist']['bio']['content'])) {
        $bio = sanitize_artist_bio($json['artist']['bio']['content']);
    }

    if ($bio === '' && isset($json['artist']['bio']['summary']) && is_string($json['artist']['bio']['summary'])) {
        $bio = sanitize_artist_bio($json['artist']['bio']['summary']);
    }

    return $bio;
}

function get_artist_bio_by_name(string $artist_name): string {
    if ($artist_name === '') {
        return '';
    }

    $cache_key = get_artist_bio_cache_key($artist_name);
    $cached_bio = get_transient($cache_key);

    if (is_string($cached_bio)) {
        return $cached_bio;
    }

    $bio = request_artist_bio_from_lastfm($artist_name, 'pt');

    if ($bio === '') {
        $bio = request_artist_bio_from_lastfm($artist_name, '');
    }

    if ($bio === '') {
        set_transient($cache_key, '', 12 * HOUR_IN_SECONDS);

        return '';
    }

    set_transient($cache_key, $bio, 30 * DAY_IN_SECONDS);

    return $bio;
}

add_action('init', function () {
    /**
     * 🧩 Shortcode: [top_artists genre="funk" limit="50"]
     *
     * @param array<string, string> $atts
     */
    function shortcode_top_artists(array $atts = []): string {
        if (is_editor_context()) {
            return '';
        }

        $script_name = 'top-artists-rank';

        wp_register_script(
            $script_name,
            get_stylesheet_directory_uri() . '/static/js/top-artists-rank.js',
            [],
            '1.0.0',
            true
        );

        wp_enqueue_script($script_name);

        $genre = isset($atts['genre']) ? sanitize_key((string) $atts['genre']) : 'geral';
        $limit = isset($atts['limit']) ? max(1, (int) $atts['limit']) : 50;

        $rows = get_top_artists_by_genre($genre, $limit);

        if (!$rows) {
            return '<div class="top-artists-empty">Não foi possível carregar o ranking agora.</div>';
        }

        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'name' => (string) ($row['artist_name'] ?? ''),
                'url' => get_artist_destination_url($row),
                'meta' => 'Pop: ' . (string) ($row['popularity'] ?? 0),
                'image_url' => (string) ($row['image_url'] ?? ''),
            ];
        }

        return render_artist_list_widget($items, 'top-artists-' . $genre);
    }
    
    function shortcode_top_artist(array $atts = []): string {
        if (is_editor_context()) {
            return '';
        }

        $genre = isset($atts['genre']) ? sanitize_key((string) $atts['genre']) : 'geral';

        $rows = get_top_artists_by_genre($genre, 1);

        if (!$rows) {
            return '<div class="top-artists-empty">Não foi possível carregar o ranking agora.</div>';
        }

        $top = $rows[0];
        $artist_bio = get_artist_bio_if_available($top);

        return render_featured_artist_box(
            $top,
            'Artista mais tocado do momento no ' . ucfirst($genre) . '!',
            $artist_bio
        );
    }

    function shortcode_artist_top_tracks(array $atts = []): string {
        if (is_editor_context()) {
            return '';
        }

        $post_id = get_the_ID();

        if (!$post_id) {
            return '';
        }

        $artist_id = isset($atts['artist_id'])
            ? sanitize_text_field((string) $atts['artist_id'])
            : (string) get_post_meta($post_id, 'artist_spotify_id', true);

        $limit = isset($atts['limit']) ? max(1, (int) $atts['limit']) : 10;

        if ($artist_id === '') {
            return '<div class="top-artists-empty">Artista não encontrado.</div>';
        }

        $tracks = get_artist_top_tracks($artist_id, $limit);

        if (!$tracks) {
            return '<div class="top-artists-empty">Não foi possível carregar as músicas agora.</div>';
        }

        $items = [];

        foreach ($tracks as $track) {
            if (!is_array($track)) {
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

        return render_artist_list_widget($items, 'artist-top-tracks-widget');
    }

    function shortcode_artist_featured(array $atts = []): string {
        if (is_editor_context()) {
            return '';
        }

        $post_id = get_the_ID();

        if (!$post_id) {
            return '';
        }

        $artist_id = (string) get_post_meta($post_id, 'artist_spotify_id', true);

        if ($artist_id === '') {
            return '<div class="top-artists-empty">Artista não encontrado.</div>';
        }

        $artist = get_artist_context_by_spotify_id($artist_id);

        if (!$artist) {
            return '<div class="top-artists-empty">Não foi possível carregar o artista agora.</div>';
        }

        $artist_name = isset($artist['name']) ? trim((string) $artist['name']) : '';

        if ($artist_name === '') {
            $artist_name = trim((string) get_the_title($post_id));
        }

        $artist['artist_name'] = $artist_name;
        $artist['spotify_url'] = $artist['spotify_url'] ?? ((string) get_post_meta($post_id, 'artist_spotify_url', true));

        $artist_bio = get_artist_bio_if_available($artist);
        $bio_to_display = $artist_bio !== '' ? $artist_bio : get_default_artist_bio_text();

        return render_featured_artist_box(
            $artist,
            '',
            $bio_to_display
        );
    }

    function shortcode_artist_seo_footer(array $atts = []): string {
        if (is_editor_context()) {
            return '';
        }

        $post_id = get_the_ID();

        if (!$post_id) {
            return '';
        }

        $artist_name = get_the_title($post_id);
        $artist_name = is_string($artist_name) ? trim($artist_name) : '';

        if ($artist_name === '') {
            return '';
        }

        ob_start(); ?>
        <h2 class="wp-block-heading">Músicas mais tocadas de <?php echo esc_html($artist_name); ?></h2>

        <p>
            Nesta página, você acompanha uma seleção atualizada com os principais sucessos do artista, reunindo as faixas que mais se destacam entre os ouvintes e que seguem em alta nas plataformas digitais.
        </p>

        <p>
            A lista ajuda a entender melhor quais músicas têm maior força no momento, quais hits continuam relevantes e quais lançamentos recentes estão ganhando mais atenção do público.
        </p>

        <p>
            Para quem busca por <?php echo esc_html($artist_name); ?>, esta página funciona como uma forma rápida de descobrir os maiores sucessos e acompanhar a fase atual da carreira do artista.
        </p>

        <p>
            Se você gosta de acompanhar rankings musicais e descobrir tendências, vale conferir também nossa página de <a href="/artistas-mais-tocados-do-brasil">artistas mais tocados do Brasil</a>, com uma visão mais ampla dos nomes que estão dominando as reproduções no país.
        </p>

        <p>
            Atualizamos o conteúdo com frequência para manter a seleção de músicas mais tocadas de <?php echo esc_html($artist_name); ?> sempre relevante para quem quer acompanhar os hits do momento.
        </p>
        <?php

        return (string) ob_get_clean();
    }

    add_shortcode('top_artists', 'shortcode_top_artists');
    add_shortcode('top_artist', 'shortcode_top_artist');
    add_shortcode('artist_top_tracks', 'shortcode_artist_top_tracks');
    add_shortcode('artist_featured', 'shortcode_artist_featured');
    add_shortcode('artist_seo_footer', 'shortcode_artist_seo_footer');

    add_filter('rank_math/frontend/description', function ($description) {
        if (!is_singular('page')) {
            return $description;
        }

        global $post;

        if (!$post instanceof \WP_Post) {
            return $description;
        }

        $artist_id = (string) get_post_meta($post->ID, 'artist_spotify_id', true);

        if ($artist_id === '') {
            return $description;
        }

        $artist_name = get_the_title($post->ID);

        return sprintf(
            'Confira as músicas mais tocadas de %s, seus maiores sucessos e hits mais populares no Spotify.',
            $artist_name
        );
    });
});