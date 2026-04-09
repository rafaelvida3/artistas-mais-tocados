<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AutomationHelpersTest extends TestCase {
    public function test_batch_window_returns_batch_next_offset_and_completion_state(): void {
        $queue = ['a', 'b', 'c', 'd'];

        $window = top_artists_build_batch_window($queue, 1, 2);

        $this->assertSame(['b', 'c'], $window['batch']);
        $this->assertSame(2, $window['processed_count']);
        $this->assertSame(3, $window['next_offset']);
        $this->assertFalse($window['is_complete']);
    }

    public function test_batch_window_marks_last_batch_as_complete(): void {
        $queue = ['a', 'b', 'c'];

        $window = top_artists_build_batch_window($queue, 2, 10);

        $this->assertSame(['c'], $window['batch']);
        $this->assertSame(3, $window['next_offset']);
        $this->assertTrue($window['is_complete']);
    }

    public function test_is_batch_lock_active_casts_truthy_and_falsy_values(): void {
        $this->assertTrue(top_artists_is_batch_lock_active(1));
        $this->assertFalse(top_artists_is_batch_lock_active(0));
        $this->assertFalse(top_artists_is_batch_lock_active(null));
    }
}
