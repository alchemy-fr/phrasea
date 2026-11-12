<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Databox\DataboxException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait DataboxResponseTrait
{
    /**
     * @param callable(): array $read
     */
    private static function fromDatabox(callable $read): JsonResponse
    {
        try {
            return new JsonResponse($read());
        } catch (DataboxException $e) {
            throw match (true) {
                $e->isAccessDenied() => new AccessDeniedHttpException('Databox denied access to this resource', $e),
                404 === $e->statusCode => new NotFoundHttpException('Not found in Databox', $e),
                default => new HttpException(502, $e->getMessage(), $e),
            };
        }
    }

    /**
     * @return array{query: ?string, ids: string[], page: int}
     */
    private static function optionsQuery(Request $request): array
    {
        $ids = $request->query->all()['ids'] ?? [];

        return [
            'query' => $request->query->has('query') ? (string) $request->query->get('query') : null,
            'ids' => array_values(array_filter(is_array($ids) ? $ids : [$ids], is_string(...))),
            'page' => max(1, $request->query->getInt('page', 1)),
        ];
    }
}
