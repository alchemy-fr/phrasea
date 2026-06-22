<?php

namespace App\OperationTask\Task;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\AttributeInterface;
use App\Entity\Core\Attribute;
use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class SwitchAttributeLocalesTask implements OperationTaskInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'switch_attribute_locales';
    }

    public function validate(array $payload): void
    {
        if (empty($payload['definitionId'] ?? null)) {
            throw new BadRequestHttpException('definitionId is required');
        }
    }

    public function handle(array $payload, RunContext $context): void
    {
        $definition = DoctrineUtil::findStrictByRepo(
            $this->attributeDefinitionRepository,
            $payload['definitionId'] ?? null,
        );

        $fromLocale = $payload['fromLocale'] ?? null;
        $toLocale = $payload['toLocale'] ?? null;

        $queryBuilder = $this->em->createQueryBuilder()
            ->update(Attribute::class, 'a')
            ->set('a.locale', ':toLocale')
            ->andWhere('a.definition = :definition')
            ->setParameter('definition', $definition)
            ->setParameter('toLocale', $toLocale);

        if (in_array($fromLocale, [
            AttributeInterface::NO_LOCALE,
            null,
        ], true)) {
            $queryBuilder
                ->andWhere('a.locale = :fromLocale OR a.locale IS NULL')
                ->setParameter('fromLocale', AttributeInterface::NO_LOCALE);
        } else {
            $queryBuilder
                ->andWhere('a.locale = :fromLocale')
                ->setParameter('fromLocale', $fromLocale);
        }

        $queryBuilder
            ->getQuery()
            ->execute();
    }
}
