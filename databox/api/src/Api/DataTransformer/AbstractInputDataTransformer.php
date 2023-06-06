<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractInputDataTransformer extends AbstractSecurityDataTransformer
{
    protected ValidatorInterface $validator;
    protected IriConverterInterface $iriConverter;
    protected EntityManagerInterface $em;

    /**
     * @param AssetInput|CollectionInput $data
     * @param Asset|Collection           $object
     */
    protected function transformPrivacy($data, $object): void
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
     * @param T $class
     *
     * @return T
     */
    protected function getEntity(string $class, string $id): object
    {
        if (str_starts_with($id, '/')) {
            $item = $this->iriConverter->getItemFromIri($id);
        } else {
            $item = $this->em->find($class, $id);
        }
        if (null === $item) {
            throw new BadRequestHttpException(sprintf('%s "%s" not found', $class, $id));
        }

        return $item;
    }

    /**
     * @required
     */
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    /**
     * @required
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * @required
     */
    public function setIriConverter(IriConverterInterface $iriConverter): void
    {
        $this->iriConverter = $iriConverter;
    }
}
