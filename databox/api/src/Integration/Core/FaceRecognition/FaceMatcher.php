<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

/**
 * Geometry of the face recognition: embedding similarity and box overlap.
 */
final class FaceMatcher
{
    /**
     * @param float[] $a
     * @param float[] $b
     *
     * @return float cosine similarity in [-1, 1]
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $n = count($a);
        if (0 === $n || $n !== count($b)) {
            return -1.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $n; ++$i) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if (0.0 === $normA || 0.0 === $normB) {
            return -1.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Intersection over union of two normalized boxes ({x, y, w, h}).
     *
     * @param array{x: float, y: float, w: float, h: float} $a
     * @param array{x: float, y: float, w: float, h: float} $b
     */
    public static function boxIou(array $a, array $b): float
    {
        $x1 = max($a['x'], $b['x']);
        $y1 = max($a['y'], $b['y']);
        $x2 = min($a['x'] + $a['w'], $b['x'] + $b['w']);
        $y2 = min($a['y'] + $a['h'], $b['y'] + $b['h']);

        $intersection = max(0.0, $x2 - $x1) * max(0.0, $y2 - $y1);
        $union = $a['w'] * $a['h'] + $b['w'] * $b['h'] - $intersection;

        return $union > 0 ? $intersection / $union : 0.0;
    }
}
