<?php

namespace Tests\Unit;

use App\Services\Seo\KeywordRankingSync;
use PHPUnit\Framework\TestCase;

class KeywordRankingSyncTest extends TestCase
{
    public function test_difficulty_labels(): void
    {
        $this->assertSame('easy', KeywordRankingSync::difficultyLabel(0));
        $this->assertSame('easy', KeywordRankingSync::difficultyLabel(29));
        $this->assertSame('medium', KeywordRankingSync::difficultyLabel('30'));
        $this->assertSame('medium', KeywordRankingSync::difficultyLabel(59));
        $this->assertSame('hard', KeywordRankingSync::difficultyLabel(60));
        $this->assertSame('hard', KeywordRankingSync::difficultyLabel(100));
    }

    public function test_unknown_difficulty_is_null_not_a_guess(): void
    {
        $this->assertNull(KeywordRankingSync::difficultyLabel(null));
        $this->assertNull(KeywordRankingSync::difficultyLabel(''));
        $this->assertNull(KeywordRankingSync::difficultyLabel('n/a'));
    }
}
