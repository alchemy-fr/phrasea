<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

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
            $item = $this->iriConverter->getResourceFromIri($id);
        } else {
            $item = $this->em->find($class, $id);
        }
        if (null === $item) {
            throw new BadRequestHttpException(sprintf('%s "%s" not found', $class, $id));
        }

        return $item;
    }

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    #[Required]
    public function setIriConverter(IriConverterInterface $iriConverter): void
    {
        $this->iriConverter = $iriConverter;
    }
}
