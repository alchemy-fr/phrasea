<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\Core\AssetFace;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AssetFaceCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return AssetFace::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset Face')
            ->setEntityLabelInPlural('Asset Faces')
            ->setSearchFields(['id', 'identity', 'model'])
            ->setPaginatorPageSize(100);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('asset'))
            ->add(TextFilter::new('identity'))
            ->add(TextFilter::new('identityOrigin'))
            ->add(TextFilter::new('model'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('asset')
            ->autocomplete()
            ->hideOnForm();
        yield TextField::new('identity');
        yield TextField::new('identityOrigin')
            ->hideOnForm();
        yield NumberField::new('identityConfidence')
            ->hideOnForm();
        yield AssociationField::new('reference')
            ->hideOnForm();
        yield IntegerField::new('position')
            ->hideOnForm();
        yield NumberField::new('confidence')
            ->hideOnForm();
        yield TextField::new('model')
            ->hideOnForm();
        yield IntegerField::new('dims')
            ->hideOnForm();
        yield JsonField::new('box')
            ->onlyOnDetail();
        yield JsonField::new('details')
            ->onlyOnDetail();
        yield JsonField::new('vector')
            ->onlyOnDetail();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
