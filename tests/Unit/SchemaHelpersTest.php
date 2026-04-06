<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchemaHelpersTest extends TestCase {
    public function test_get_genre_label_returns_expected_labels(): void {
        $this->assertSame('Funk', get_genre_label('funk'));
        $this->assertSame('Brasil', get_genre_label('geral'));
        $this->assertSame('Trap', get_genre_label('trap'));
    }

    public function test_get_genre_label_falls_back_to_ucfirst_for_unknown_genre(): void {
        $this->assertSame('Jazz', get_genre_label('jazz'));
    }

    public function test_build_dynamic_schema_title_returns_correct_title_for_geral(): void {
        $this->assertSame(
            'Top 10 Artistas no Brasil',
            build_dynamic_schema_title('geral', 10)
        );
    }

    public function test_build_dynamic_schema_title_returns_correct_title_for_specific_genre(): void {
        $this->assertSame(
            'Top 50 Artistas de Funk Mais Tocados',
            build_dynamic_schema_title('funk', 50)
        );
    }

    public function test_get_ranking_schema_title_returns_correct_title_for_geral(): void {
        $this->assertSame(
            'Top 10 Artistas no Brasil',
            get_ranking_schema_title('geral')
        );
    }

    public function test_get_ranking_schema_title_returns_correct_title_for_specific_genre(): void {
        $this->assertSame(
            'Top 10 Artistas de Pagode no Brasil',
            get_ranking_schema_title('pagode')
        );
    }
}