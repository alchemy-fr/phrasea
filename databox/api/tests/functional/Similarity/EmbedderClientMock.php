<?php

declare(strict_types=1);

namespace App\Tests\Functional\Similarity;

use App\Service\Vector\EmbedderClient;

class EmbedderClientMock extends EmbedderClient
{
    final public const int DIMS = 512;

    /**
     * @var string[] paths of the embedded images
     */
    private array $calls = [];

    public function __construct()
    {
    }

    public function embedImageFile(string $path): array
    {
        $this->calls[] = $path;

        $vector = array_fill(0, self::DIMS, 0.0);
        $vector[0] = 1.0;

        return [
            'vector' => $vector,
            'model' => 'mock',
            'dim' => self::DIMS,
        ];
    }

    public function embedText(string $text): array
    {
        throw new \LogicException('Not mocked');
    }

    /**
     * @return string[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
