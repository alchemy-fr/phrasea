<?php

declare(strict_types=1);

namespace App\Tests\Face;

use App\Service\Face\FaceRecognitionClient;

class FaceRecognitionClientMock extends FaceRecognitionClient
{
    private array $faces = [];
    private array $calls = [];

    public function __construct()
    {
    }

    /**
     * @param array<array{box: array, confidence: float, embedding: float[], age?: int, gender?: string}> $faces
     */
    public function setFaces(array $faces): void
    {
        $this->faces = $faces;
    }

    public function detectFaces(string $path): array
    {
        $this->calls[] = $path;

        return [
            'model' => 'mock',
            'dim' => count($this->faces[0]['embedding'] ?? []),
            'width' => 100,
            'height' => 100,
            'faces' => $this->faces,
        ];
    }

    public function getCalls(): array
    {
        return $this->calls;
    }
}
