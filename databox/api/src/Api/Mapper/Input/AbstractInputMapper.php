<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\EntityIriConverter;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractInputMapper
{
    use SecurityAwareTrait;

    protected EntityManagerInterface $em;
    protected EntityIriConverter $entityIriConverter;
    protected ValidatorInterface $validator;

    protected function transformPrivacy(AssetInput|CollectionInput $data, Asset|Collection $object): void
    {
        if ($this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $object)) {
            if (null !== $data->privacy) {
                $object->setPrivacy($data->privacy);
            } elseif (null !== $data->privacyLabel) {
                $constantName = WorkspaceItemPrivacyInterface::class.'::'.strtoupper($data->privacyLabel);
                if (!defined($constantName)) {
                    throw new BadRequestHttpException(sprintf('Invalid privacyLabel "%s"', $data->privacyLabel));
                }
                $object->setPrivacy(constant($constantName));
            }
        }
    }

    /**
     * Whether a property was sent, null included (a merge patch removes a value with null).
     * A null value only counts when its key is at the root of the request body.
     */
    protected static function hasKey(string $key, mixed $value, array $context): bool
    {
        if (null !== $value) {
            return true;
        }

        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            return false;
        }

        try {
            return array_key_exists($key, $request->toArray());
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function getEntity(string $class, string $id): object
    {
        try {
            return $this->entityIriConverter->getItemFromIri($class, $id);
        } catch (ItemNotFoundException $e) {
            throw new BadRequestHttpException(sprintf('%s "%s" not found', $class, $id), $e);
        }
    }

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setEntityIriConverter(EntityIriConverter $entityIriConverter): void
    {
        $this->entityIriConverter = $entityIriConverter;
    }

    #[Required]
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }
}
