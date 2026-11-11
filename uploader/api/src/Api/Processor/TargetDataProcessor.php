<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\DTO\Input\TargetDataInput;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class TargetDataProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param TargetDataInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $target = DoctrineUtil::findStrict($this->em, Target::class, (string) $uriVariables['id'], throw404: true);
        $target->setData($data->data);
        $this->em->flush();

        // The constructor would turn null into {}
        return (new JsonResponse())->setData($target->getData());
    }
}
