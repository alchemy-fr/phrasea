<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Filter\Configurator;

use Alchemy\AdminBundle\Filter\ChildPropertyEntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;

final class ChildPropertyEntityConfigurator implements FilterConfiguratorInterface
{
    private EntityFactory $entityFactory;

    public function __construct(EntityFactory $entityFactory)
    {
        $this->entityFactory = $entityFactory;
    }

    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return ChildPropertyEntityFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $propertyName = $filterDto->getProperty();

        if (!$entityDto->isAssociation($propertyName)) {
            return;
        }

        $doctrineMetadata = $entityDto->getPropertyMetadata($propertyName);

        $propertyEntityFqcn = $doctrineMetadata->get('targetEntity');

        $propertyEntityMetadata = $this->entityFactory->getEntityMetadata($propertyEntityFqcn);

        $propertyEntityDto = new EntityDto($propertyEntityFqcn, $propertyEntityMetadata);

        // get the child property name set from filter
        $childPropertyName = $filterDto->getFormTypeOption('value_type_options.attr.data-child-property');

        if (!$propertyEntityDto->isAssociation($childPropertyName)) {
            return;
        }

        $childPropertyDoctrineMetadata = $propertyEntityDto->getPropertyMetadata($childPropertyName);

        // TODO: add the 'em' form type option too?
        $filterDto->setFormTypeOptionIfNotSet('value_type_options.class', $childPropertyDoctrineMetadata->get('targetEntity'));
        $filterDto->setFormTypeOptionIfNotSet('value_type_options.multiple', $entityDto->isToManyAssociation($propertyName));
        $filterDto->setFormTypeOptionIfNotSet('value_type_options.attr.data-ea-widget', 'ea-autocomplete');

        if ($propertyEntityDto->isToOneAssociation($childPropertyName)) {
            // don't show the 'empty value' placeholder when all join columns are required,
            // because an empty filter value would always return no result
            $numberOfRequiredJoinColumns = \count(array_filter(
                $doctrineMetadata->get('joinColumns'),
                static function (array $joinColumn): bool {
                    $isNullable = $joinColumn['nullable'] ?? false;

                    return false === $isNullable;
                }
            ));

            $someJoinColumnsAreNullable = \count($childPropertyDoctrineMetadata->get('joinColumns')) !== $numberOfRequiredJoinColumns;

            if ($someJoinColumnsAreNullable) {
                $filterDto->setFormTypeOptionIfNotSet('value_type_options.placeholder', 'label.form.empty_value');
            }
        }
    }
}
