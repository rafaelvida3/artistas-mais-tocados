<?php

declare(strict_types=1);

function top_artists_is_batch_lock_active(mixed $lock_value): bool {
    return (bool) $lock_value;
}

/**
 * @return array{batch: array<int, mixed>, processed_count: int, next_offset: int, is_complete: bool}
 */
function top_artists_build_batch_window(array $queue, int $offset, int $batch_size): array {
    $safe_offset = max(0, $offset);
    $safe_batch_size = max(1, $batch_size);
    $batch = array_slice($queue, $safe_offset, $safe_batch_size);
    $processed_count = count($batch);
    $next_offset = $safe_offset + $processed_count;

    return [
        'batch' => $batch,
        'processed_count' => $processed_count,
        'next_offset' => $next_offset,
        'is_complete' => $next_offset >= count($queue),
    ];
}
