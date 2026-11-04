<?php

declare(strict_types=1);

namespace App\Elasticsearch\Exception;

use Elastic\Elasticsearch\Exception\ClientResponseException;

/**
 * Raised when a search targets an Elasticsearch index that does not exist.
 *
 * This happens between a workspace creation (or a failed populate) and the
 * moment the index alias is actually in place: the index is missing, not the
 * data. Callers on the API path turn it into an empty result set instead of a
 * 500, so a tenant whose index is not ready yet still gets a usable page.
 */
final class MissingSearchIndexException extends \RuntimeException
{
    private const string ES_ERROR_TYPE = 'index_not_found_exception';

    public function __construct(?string $indexName, ?\Throwable $previous = null)
    {
        parent::__construct(
            null !== $indexName
                ? \sprintf('Search index "%s" does not exist', $indexName)
                : 'Search index does not exist',
            0,
            $previous
        );
    }

    /**
     * Returns a wrapping exception when $e reports a missing index, null otherwise.
     */
    public static function tryFrom(\Throwable $e): ?self
    {
        if ($e instanceof ClientResponseException && 404 === $e->getCode()) {
            $body = json_decode((string) $e->getResponse()->getBody(), true);
            $error = \is_array($body) ? ($body['error'] ?? null) : null;

            if (\is_array($error) && self::ES_ERROR_TYPE === ($error['type'] ?? null)) {
                $index = $error['index'] ?? null;

                return new self(\is_string($index) ? $index : null, $e);
            }

            return null;
        }

        // Older clients and Elastica wrappers only carry the type in the message.
        if (str_contains($e->getMessage(), self::ES_ERROR_TYPE)
            || str_contains($e->getMessage(), 'no such index')
        ) {
            return new self(null, $e);
        }

        return null;
    }
}
