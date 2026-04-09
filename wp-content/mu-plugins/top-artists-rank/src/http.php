<?php

declare(strict_types=1);

use WpOrg\Requests\Exception as RequestsException;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Response;

/**
 * Executes multiple HTTP requests and normalizes error handling.
 *
 * @param array<string, array<string, mixed>> $requests
 * @return array<string, mixed>
 */
function top_artists_request_multiple_json(
    array $requests,
    callable $on_success,
    callable $make_default,
): array {
    /** @var array<string, mixed> $result */
    $result = [];

    if ($requests === []) {
        return $result;
    }

    $responses = Requests::request_multiple($requests);

    foreach ($responses as $key => $response) {
        $url = isset($requests[$key]['url']) ? (string) $requests[$key]['url'] : '';
        $default_value = $make_default((string) $key);

        if ($response instanceof RequestsException) {
            error_log(sprintf('[top-artists] API ERROR %s :: %s', $url, $response->getMessage()));
            $result[$key] = $default_value;
            continue;
        }

        if (! $response instanceof Response) {
            error_log(sprintf('[top-artists] API ERROR %s :: unexpected response type', $url));
            $result[$key] = $default_value;
            continue;
        }

        $status_code = (int) $response->status_code;

        if ($status_code !== 200) {
            $retry_after = $response->headers['retry-after'] ?? null;
            $suffix = $retry_after ? ' :: RETRY-AFTER ' . $retry_after : '';
            error_log(
                sprintf('[top-artists] API ERROR %s :: CODE %d%s', $url, $status_code, $suffix),
            );
            $result[$key] = $default_value;
            continue;
        }

        $json = json_decode($response->body, true);

        if (! is_array($json)) {
            error_log(sprintf('[top-artists] API ERROR %s :: invalid JSON', $url));
            $result[$key] = $default_value;
            continue;
        }

        try {
            $result[$key] = $on_success((string) $key, $json, $requests[$key]);
        } catch (\Throwable $throwable) {
            error_log(
                sprintf(
                    '[top-artists] PARSE ERROR key=%s :: %s',
                    (string) $key,
                    $throwable->getMessage(),
                ),
            );
            $result[$key] = $default_value;
        }
    }

    return $result;
}

function top_artists_get_spotify_token(): string {
    $cached_token = get_transient('spotify_access_token');

    if (is_string($cached_token) && $cached_token !== '') {
        return $cached_token;
    }

    $client_id = defined('SPOTIFY_CLIENT_ID') ? (string) SPOTIFY_CLIENT_ID : '';
    $client_secret = defined('SPOTIFY_CLIENT_SECRET') ? (string) SPOTIFY_CLIENT_SECRET : '';

    if ($client_id === '' || $client_secret === '') {
        return '';
    }

    $requests = [
        'token' => [
            'url' => 'https://accounts.spotify.com/api/token',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'type' => Requests::POST,
            'data' => [
                'grant_type' => 'client_credentials',
            ],
            'options' => [
                'timeout' => TOP_ARTISTS_REQUEST_TIMEOUT,
                'redirection' => 2,
            ],
        ],
    ];

    $on_success = static function (string $key, array $json): string {
        $token = isset($json['access_token']) ? (string) $json['access_token'] : '';

        if ($token === '') {
            return '';
        }

        $expires_in = isset($json['expires_in']) && is_numeric($json['expires_in'])
            ? (int) $json['expires_in']
            : 3600;

        set_transient('spotify_access_token', $token, max(300, $expires_in - 60));

        return $token;
    };

    $result = top_artists_request_multiple_json(
        $requests,
        $on_success,
        static fn (string $key): string => '',
    );

    return isset($result['token']) && is_string($result['token']) ? $result['token'] : '';
}
