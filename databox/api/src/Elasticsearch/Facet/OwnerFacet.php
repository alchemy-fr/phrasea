<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;

final class OwnerFacet extends AbstractFacet
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    )
    {
    }

    public function normalizeBucket(array $bucket): ?array
    {
        $user = $this->userRepository->getUser($bucket['key']);
        if (null === $user) {
            return null;
        }

        $newKey = [
            'value' => $bucket['key'],
            'label' => $this->resolveLabel($user),
        ];

        $bucket['key'] = $newKey;

        return $bucket;
    }

    /**
     * @param array $value
     */
    public function resolveLabel($value): string
    {
        return $value['username'] ?? $value['id'];
    }

    public function getType(): string
    {
        return KeywordAttributeType::NAME;
    }

    protected function resolveKey($value): string
    {
        return (string) $value;
    }

    public function getFieldName(): string
    {
        return 'ownerId';
    }

    public static function getKey(): string
    {
        return '@owner';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getPrivacy();
    }

    protected function getAggregationTitle(): string
    {
        return 'Owner';
    }

    protected function getAggregationSize(): int
    {
        return count(WorkspaceItemPrivacyInterface::KEYS);
    }
}
