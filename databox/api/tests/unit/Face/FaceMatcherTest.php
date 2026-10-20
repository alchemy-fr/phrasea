<?php

declare(strict_types=1);

namespace App\Tests\Unit\Face;

use App\Integration\Core\FaceRecognition\FaceMatcher;
use PHPUnit\Framework\TestCase;

class FaceMatcherTest extends TestCase
{
    public function testCosineSimilarity(): void
    {
        $this->assertEqualsWithDelta(1.0, FaceMatcher::cosineSimilarity([1, 0], [2, 0]), 1e-9);
        $this->assertEqualsWithDelta(0.0, FaceMatcher::cosineSimilarity([1, 0], [0, 1]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, FaceMatcher::cosineSimilarity([1, 0], [-1, 0]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, FaceMatcher::cosineSimilarity([1, 0], [1, 0, 0]), 1e-9, 'Dimension mismatch never matches');
        $this->assertEqualsWithDelta(-1.0, FaceMatcher::cosineSimilarity([0, 0], [1, 0]), 1e-9, 'Null vector never matches');
    }

    public function testBoxIou(): void
    {
        $a = ['x' => 0.0, 'y' => 0.0, 'w' => 0.5, 'h' => 0.5];
        $this->assertEqualsWithDelta(1.0, FaceMatcher::boxIou($a, $a), 1e-9);
        $this->assertEqualsWithDelta(0.0, FaceMatcher::boxIou($a, ['x' => 0.5, 'y' => 0.5, 'w' => 0.5, 'h' => 0.5]), 1e-9);
        // Half overlap: intersection .125, union .375
        $this->assertEqualsWithDelta(1 / 3, FaceMatcher::boxIou($a, ['x' => 0.25, 'y' => 0.0, 'w' => 0.5, 'h' => 0.5]), 1e-9);
    }
}
