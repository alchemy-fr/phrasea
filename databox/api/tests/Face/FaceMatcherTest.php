<?php

declare(strict_types=1);

namespace App\Tests\Face;

use App\Entity\Core\AssetFace;
use App\Service\Face\FaceMatcher;
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

    public function testFindBestMatchReturnsTheClosestReferenceAboveThreshold(): void
    {
        $alice = $this->createReference('Alice', [1.0, 0.0, 0.0]);
        $bob = $this->createReference('Bob', [0.0, 1.0, 0.0]);
        $aliceTwin = $this->createReference('Alice', [0.95, 0.05, 0.0]);

        $matcher = new FaceMatcher();

        $match = $matcher->findBestMatch([0.9, 0.1, 0.0], [$bob, $alice, $aliceTwin], 0.5);
        $this->assertNotNull($match);
        $this->assertSame('Alice', $match['identity']);
        $this->assertSame($aliceTwin, $match['face']);
        $this->assertGreaterThan(0.99, $match['similarity']);

        $this->assertNull($matcher->findBestMatch([0.0, 0.0, 1.0], [$alice, $bob], 0.5), 'No reference above the threshold');
        $this->assertNull($matcher->findBestMatch([1.0, 0.0, 0.0], [], 0.5), 'No reference at all');
    }

    private function createReference(string $identity, array $vector): AssetFace
    {
        $face = new AssetFace();
        $face->setVector($vector);
        $face->setIdentity($identity, AssetFace::IDENTITY_ORIGIN_USER, 1.0);

        return $face;
    }
}
