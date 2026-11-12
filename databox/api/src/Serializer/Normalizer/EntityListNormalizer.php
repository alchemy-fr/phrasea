<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\EntityList;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\EntityListVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Adds the "capabilities" of the current user to a list served by the entity list endpoints.
 * A list nested in another resource (e.g. an attribute definition) goes without them,
 * which saves four votes per nested list.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => 1042])]
final class EntityListNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string CONTEXT_KEY = 'entity_list_capabilities';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * @param EntityList $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::CONTEXT_KEY] = $data->getId();

        $normalized = $this->normalizer->normalize($data, $format, $context);
        if (!\is_array($normalized) || !$this->isServedAsRootResource($context)) {
            return $normalized;
        }

        $normalized['capabilities'] = [
            'edit' => $this->security->isGranted(AbstractVoter::EDIT, $data),
            'editValues' => $this->security->isGranted(EntityListVoter::EDIT_VALUES, $data),
            'delete' => $this->security->isGranted(AbstractVoter::DELETE, $data),
            'editPermissions' => $this->security->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
        ];

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof EntityList && ($context[self::CONTEXT_KEY] ?? null) !== $data->getId();
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            EntityList::class => false,
        ];
    }

    private function isServedAsRootResource(array $context): bool
    {
        $operation = $context['root_operation'] ?? $context['operation'] ?? null;

        return $operation instanceof Operation && EntityList::class === $operation->getClass();
    }
}
