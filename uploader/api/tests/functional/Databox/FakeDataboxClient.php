<?php

declare(strict_types=1);

namespace App\Tests\Functional\Databox;

use App\Databox\DataboxClientInterface;
use App\Databox\DataboxException;

final class FakeDataboxClient implements DataboxClientInterface
{
    /**
     * @var list<array{path: string, query: array}>
     */
    public array $calls = [];

    /**
     * @param array<string, array|int|callable(array): array> $routes the answer by path: a JSON body, or an error status
     */
    public function __construct(private readonly array $routes)
    {
    }

    public function get(string $path, array $query = []): array
    {
        $this->calls[] = ['path' => $path, 'query' => $query];

        $answer = $this->routes[$path] ?? 404;
        if (is_int($answer)) {
            throw new DataboxException(sprintf('Databox answered %d on %s', $answer, $path), $answer);
        }

        return is_callable($answer) ? $answer($query) : $answer;
    }
}
