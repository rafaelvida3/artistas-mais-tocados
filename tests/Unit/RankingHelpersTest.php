<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RankingHelpersTest extends TestCase {
    public function test_normalize_string_removes_accents_and_trims(): void {
        $this->assertSame('eabest', top_artists_normalize_string(' ÉaBest '));
        $this->assertSame('funk carioca', top_artists_normalize_string('Funk Carioca'));
    }

    public function test_artist_matches_genre_returns_true_for_geral(): void {
        $this->assertTrue(top_artists_artist_matches_genre('geral', []));
    }

    public function test_artist_matches_genre_returns_true_when_first_genre_matches(): void {
        $artist_genres = ['funk carioca', 'baile funk'];

        $this->assertTrue(top_artists_artist_matches_genre('funk', $artist_genres));
    }

    public function test_artist_matches_genre_returns_true_when_second_and_third_genres_match(): void {
        $artist_genres = ['pop', 'trap brasileiro', 'trap'];

        $this->assertTrue(top_artists_artist_matches_genre('trap', $artist_genres));
    }

    public function test_artist_matches_genre_returns_false_when_only_second_genre_matches(): void {
        $artist_genres = ['pop', 'trap brasileiro', 'mpb'];

        $this->assertFalse(top_artists_artist_matches_genre('trap', $artist_genres));
    }

    public function test_secondary_genre_match_rule_is_explicit(): void {
        $needles = ['trap brasileiro', 'trap'];

        $this->assertFalse(
            top_artists_has_confirmed_secondary_genre_match(['pop', 'trap brasileiro'], $needles)
        );
        $this->assertTrue(
            top_artists_has_confirmed_secondary_genre_match(
                ['pop', 'trap brasileiro', 'trap'],
                $needles,
            )
        );
    }

    public function test_artist_matches_genre_returns_false_for_empty_genres(): void {
        $this->assertFalse(top_artists_artist_matches_genre('funk', []));
    }

    public function test_artist_is_denied_for_genre_returns_true_for_known_denied_artist(): void {
        $this->assertTrue(
            top_artists_artist_is_denied_for_genre('1APqNiQUA2XpwLEbywSWmZ', 'funk')
        );
    }

    public function test_artist_is_denied_for_genre_returns_false_for_unknown_artist(): void {
        $this->assertFalse(top_artists_artist_is_denied_for_genre('unknown-id', 'funk'));
    }

    public function test_parse_release_date_to_ts_handles_year_precision(): void {
        $expected = strtotime('2024-01-01');

        $this->assertSame($expected, top_artists_parse_release_date_to_ts('2024', 'year'));
    }

    public function test_parse_release_date_to_ts_handles_month_precision(): void {
        $expected = strtotime('2024-05-01');

        $this->assertSame($expected, top_artists_parse_release_date_to_ts('2024-05', 'month'));
    }

    public function test_parse_release_date_to_ts_handles_day_precision(): void {
        $expected = strtotime('2024-05-20');

        $this->assertSame($expected, top_artists_parse_release_date_to_ts('2024-05-20', 'day'));
    }

    public function test_parse_release_date_to_ts_returns_zero_for_null(): void {
        $this->assertSame(0, top_artists_parse_release_date_to_ts(null, null));
    }

    public function test_label_name_is_suspect_returns_true_for_label_like_names(): void {
        $this->assertTrue(top_artists_label_name_is_suspect('Love Funk Records'));
        $this->assertTrue(top_artists_label_name_is_suspect('Mainstreet Ent'));
    }

    public function test_label_name_is_suspect_returns_false_for_regular_artist_name(): void {
        $this->assertFalse(top_artists_label_name_is_suspect('MC Cabelinho'));
    }
}
