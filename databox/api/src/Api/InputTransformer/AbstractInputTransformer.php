<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Exception\ItemNotFoundException;
use App\Api\EntityIriConverter;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractInputTransformer implements InputTransformerInterface
{
    use SecurityAwareTrait;

    protected EntityManagerInterface $em;
    protected EntityIriConverter $entityIriConverter;

    protected function transformPrivacy(AssetInput|CollectionInput $data, Asset|Collection $object): void
    {
        if (null !== $data->privacy) {
            $object->setPrivacy($data->privacy);
        }
        if (null !== $data->privacyLabel) {
            $constantName = WorkspaceItemPrivacyInterface::class.'::'.strtoupper($data->privacyLabel);
            if (!defined($constantName)) {
                throw new BadRequestHttpException(sprintf('Invalid privacyLabel "%s"', $data->privacyLabel));
            }
            $object->setPrivacy(constant($constantName));
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
}
