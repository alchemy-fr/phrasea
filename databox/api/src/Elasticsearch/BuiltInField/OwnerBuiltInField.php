<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OwnerBuiltInField extends AbstractBuiltInAttribute
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        #[Autowire(env: 'API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE')]
        private readonly string $ownerPropertyRequiredRole,

    ) {
    }

    public function denormalizeValue(?string $value): mixed
    {
        if (empty($value)) {
            return null;
        }

        $users = $this->userRepository->getUsersByIds([$value]);
        if (empty($users)) {
            return null;
        }

        return [
            'id' => $value,
            'username' => $this->resolveLabel($users[$value]),
        ];
    }

    public function normalizeBuckets(array $buckets): array
    {
        $users = $this->userRepository->getUsersByIds(array_map(function (array $bucket): string {
            return $bucket['key'];
        }, $buckets));

        return array_map(function (array $bucket) use ($users): ?array {
            $user = $users[$bucket['key']] ?? null;
            if (null === $user) {
                return null;
            }

            $newKey = [
                'value' => $bucket['key'],
                'label' => $this->resolveLabel($user),
            ];

            $bucket['key'] = $newKey;

            return $bucket;
        }, $buckets);
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
        return (string) $value['id'];
    }

    public static function getName(): string
    {
        return 'ownerId';
    }

    public static function getKey(): string
    {
        return '@owner';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getOwnerId();
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'owner';
    }

    public function isFacet(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return empty($this->ownerPropertyRequiredRole) || $this->hasRole($this->ownerPropertyRequiredRole);
    }
}
