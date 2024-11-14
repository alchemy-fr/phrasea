<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Filter;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

final class ChildPropertyEntityFilter implements FilterInterface
{
    use FilterTrait;

    private string $realPropertyName;
    private string $subPropertyName;


    public static function new(string $propertyName, string $childPropertyName, string $entityClass, $label = null): self
    {
        $label = null == $label ? $childPropertyName : $label;

        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setRealPropertyName($propertyName)
            ->setSubPropertyName($childPropertyName)
            ->setProperty(sprintf('%s__%s', $propertyName, $childPropertyName))
            ->setLabel($label)
            ->setFormType(EntityFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle')
            ->setFormTypeOption('value_type_options.attr.data-child-property', $childPropertyName)
            ->setFormTypeOption('value_type_options.class', $entityClass)
            ;
    }

    /**
     *  Some code logic from EntityFilter.
     */
    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        // the 'ea_' prefix is needed to avoid errors when using reserved words as assocAlias ('order', 'group', etc.)
        // see https://github.com/EasyCorp/EasyAdminBundle/pull/4344
        $assocAlias = 'ea_'.$filterDataDto->getParameterName();
        $childAssocAlias = 'ea_'.$filterDataDto->getParameter2Name();

        $property = $this->realPropertyName;
        $comparison = $filterDataDto->getComparison();
        $parameterName = $filterDataDto->getParameterName();
        $value = $filterDataDto->getValue();
        $isMultiple = $filterDataDto->getFormTypeOption('value_type_options.multiple');

        $childPropertyName = $this->subPropertyName;
        $doctrineMetadata = $entityDto->getPropertyMetadata($property);
        $entityManager = $queryBuilder->getEntityManager();
        $propertyEntityFqcn = $doctrineMetadata->get('targetEntity');
        /** @var ClassMetadata $entityMetadata */
        $propertyEntityMetadata = $entityManager->getClassMetadata($propertyEntityFqcn);
        $propertyEntityDto = new EntityDto($propertyEntityFqcn, $propertyEntityMetadata);

        $queryBuilder->leftJoin(sprintf('%s.%s', $alias, $property), $assocAlias);
        // do the clause where with the childProperty not with the property like in EntityFilter
        $queryBuilder->leftJoin(sprintf('%s.%s', $assocAlias, $childPropertyName), $childAssocAlias);

        if ($entityDto->isToManyAssociation($property) && $propertyEntityDto->isToManyAssociation($childPropertyName)) {
            if (0 === \count($value)) {
                $queryBuilder->andWhere(sprintf('%s %s', $childAssocAlias, $comparison));
            } else {
                $orX = new Orx();
                $orX->add(sprintf('%s %s (:%s)', $childAssocAlias, $comparison, $parameterName));
                if ('NOT IN' === $comparison) {
                    $orX->add(sprintf('%s IS NULL', $assocAlias));
                }
                $queryBuilder->andWhere($orX)
                    ->setParameter($parameterName, $this->processParameterValue($queryBuilder, $value));
            }
        } elseif (null === $value || ($isMultiple && 0 === \count($value))) {
            $queryBuilder->andWhere(sprintf('%s.%s %s', $assocAlias, $childPropertyName, $comparison));
        } else {
            $orX = new Orx();
            $orX->add(sprintf('%s.%s %s (:%s)', $assocAlias, $childPropertyName, $comparison, $parameterName));
            if (ComparisonType::NEQ === $comparison) {
                $orX->add(sprintf('%s.%s IS NULL', $assocAlias, $childPropertyName));
            }
            $queryBuilder->andWhere($orX)
                ->setParameter($parameterName, $this->processParameterValue($queryBuilder, $value));
        }
    }

    private function processParameterValue(QueryBuilder $queryBuilder, mixed $parameterValue): mixed
    {
        if (!$parameterValue instanceof ArrayCollection) {
            return $this->processSingleParameterValue($queryBuilder, $parameterValue);
        }

        return $parameterValue->map(fn ($element) => $this->processSingleParameterValue($queryBuilder, $element));
    }

    /**
     * If the parameter value is a bound entity or a collection of bound entities
     * and the PHP value of the entity's identifier is either of type
     * "Symfony\Component\Uid\Uuid" or "Symfony\Component\Uid\Ulid" defined in
     * symfony/uid then the parameter value is converted from the entity to the
     * database value of its primary key.
     *
     * Otherwise, the parameter value is not processed.
     *
     * For example, if the used platform is MySQL:
     *
     *      App\Entity\Category {#1040 ▼
     *          -id: Symfony\Component\Uid\UuidV6 {#1046 ▼
     *              #uid: "1ec4d51f-c746-6f60-b698-634384c1b64c"
     *          }
     *          -title: "cat 2"
     *      }
     *
     *  gets processed to a binary value:
     *
     *      b"\x1EÄÕ\x1FÇFo`¶˜cC„Á¶L"
     */
    private function processSingleParameterValue(QueryBuilder $queryBuilder, mixed $parameterValue): mixed
    {
        $entityManager = $queryBuilder->getEntityManager();

        try {
            $classMetadata = $entityManager->getClassMetadata($parameterValue::class);
        } catch (\Throwable) {
            // only reached if $parameterValue does not contain an object of a managed
            // entity, return as we only need to process bound entities
            return $parameterValue;
        }

        try {
            $identifierType = $classMetadata->getTypeOfField($classMetadata->getSingleIdentifierFieldName());
        } catch (MappingException) {
            throw new \RuntimeException(sprintf('The EntityFilter does not support entities with a composite primary key or entities without an identifier. Please check your entity "%s".', $parameterValue::class));
        }

        $identifierValue = $entityManager->getUnitOfWork()->getSingleIdentifierValue($parameterValue);

        if ($identifierValue instanceof Uuid || $identifierValue instanceof Ulid) {
            try {
                return Type::getType($identifierType)->convertToDatabaseValue(
                    $identifierValue,
                    $entityManager->getConnection()->getDatabasePlatform()
                );
            } catch (\Throwable) {
                // if the conversion fails we cannot process the uid parameter value
            }
        }

        return $parameterValue;
    }

    public function setRealPropertyName(string $realPropertyName): self
    {
        $this->realPropertyName = $realPropertyName;

        return $this;
    }

    public function setSubPropertyName(string $subPropertyName): self
    {
        $this->subPropertyName = $subPropertyName;

        return $this;
    }
}
