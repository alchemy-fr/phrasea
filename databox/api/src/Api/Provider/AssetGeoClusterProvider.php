<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ParameterValuesTrait;
use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\Geo\GeoClusterResult;
use App\Elasticsearch\NoWorkspaceAllowedException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AssetGeoClusterProvider implements ProviderInterface
{
    use ParameterValuesTrait;

    public function __construct(
        private AssetSearch $assetSearch,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        $options = self::getParameterValues($operation);
        $field = $options['field'] ?? null;
        if (!is_string($field) || '' === $field) {
            throw new BadRequestHttpException('Missing "field" parameter');
        }
        $precision = (int) ($options['precision'] ?? 0);
        $bounds = isset($options['bounds']) ? self::parseBounds($options['bounds']) : null;
        unset($options['field'], $options['precision'], $options['bounds']);

        try {
            $result = $this->assetSearch->geoClusters($userId, $groupIds, $field, $precision, $bounds, $options);
        } catch (NoWorkspaceAllowedException) {
            $result = new GeoClusterResult(0, 0, null, []);
        }

        return new JsonResponse($result);
    }

    /**
     * @return array{top: float, left: float, bottom: float, right: float}
     */
    private static function parseBounds(mixed $value): array
    {
        $parts = is_string($value) ? explode(',', $value) : [];
        if (4 !== count($parts) || array_filter($parts, fn (string $p): bool => !is_numeric(trim($p)))) {
            throw new BadRequestHttpException('Invalid "bounds" parameter: expected "top,left,bottom,right"');
        }
        [$top, $left, $bottom, $right] = array_map(fn (string $p): float => (float) $p, $parts);

        if ($top < $bottom || abs($top) > 90 || abs($bottom) > 90 || abs($left) > 180 || abs($right) > 180) {
            throw new BadRequestHttpException('Invalid "bounds" parameter: out of range');
        }

        return [
            'top' => $top,
            'left' => $left,
            'bottom' => $bottom,
            'right' => $right,
        ];
    }
}
