<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\EntityIriConverter;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Util\SecurityAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractInputProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    private ValidatorInterface $validator;
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

    abstract protected function transform(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed;

    final public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->validator->validate($data, $context);
        $object = $this->transform($data, $operation, $uriVariables, $context);

        $this->validator->validate($object, $context);

        $this->em->persist($object);
        $this->em->flush();

        return $object;
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
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    #[Required]
    public function setEntityIriConverter(EntityIriConverter $entityIriConverter): void
    {
        $this->entityIriConverter = $entityIriConverter;
    }
}
