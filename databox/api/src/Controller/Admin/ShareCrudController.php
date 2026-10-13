<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Core\Share;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ShareCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Share::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Share')
            ->setEntityLabelInPlural('Shares')
            ->setPaginatorPageSize(100);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('assets'))
            ->add($this->userChoiceFilter->createFilter('ownerId'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield AssociationField::new('assets')
            ->autocomplete();
        // Through the adder and remover, which maintain renditionsRestricted
        yield AssociationField::new('renditionDefinitions', 'Shared renditions')
            ->setFormTypeOption('by_reference', false)
            ->setHelp('Empty: every rendition of a public policy')
            ->hideOnIndex();
        yield BooleanField::new('renditionsRestricted', 'Restricted renditions')
            ->setHelp('Only the shared renditions are delivered, none if they were all deleted')
            ->renderAsSwitch(false)
            ->hideOnForm()
            ->hideOnIndex();
        yield CodeField::new('token');
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield DateTimeField::new('startsAt');
        yield DateTimeField::new('expiresAt');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
