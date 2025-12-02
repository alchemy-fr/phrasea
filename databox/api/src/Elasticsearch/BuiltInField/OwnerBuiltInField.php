<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;

final class OwnerBuiltInField extends AbstractBuiltInField
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
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

    protected function getAggregationTranslationKey(): string
    {
        return 'owner';
    }

    public function isFacet(): bool
    {
        return false;
    }
}
