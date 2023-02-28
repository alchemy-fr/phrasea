<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Core\Attribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;


class AttributeCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Attribute::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribute')
            ->setEntityLabelInPlural('Attribute')
            ->setSearchFields(['id', 'locale', 'position', 'translationId', 'translationOriginHash', 'value', 'origin', 'originVendor', 'originUserId', 'originVendorContext', 'coordinates', 'status', 'confidence'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        $definition = AssociationField::new('definition');
        $value = TextField::new('value');
        $locale = TextField::new('locale');
        $locked = Field::new('locked');
        $origin = IntegerField::new('origin');
        $originVendor = TextField::new('originVendor');
        $originVendorContext = TextareaField::new('originVendorContext');
        $id = IdField::new();
        $position = IntegerField::new('position');
        $translationId = Field::new('translationId');
        $translationOriginHash = TextField::new('translationOriginHash');
        $originUserId = Field::new('originUserId');
        $coordinates = TextareaField::new('coordinates');
        $status = IntegerField::new('status');
        $confidence = NumberField::new('confidence');
        $createdAt = DateTimeField::new('createdAt');
        $updatedAt = DateTimeField::new('updatedAt');
        $asset = AssociationField::new('asset');
        $translationOrigin = AssociationField::new('translationOrigin');
        $translations = AssociationField::new('translations');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $asset, $definition, $value, $locale, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $locale, $locked, $position, $translationId, $translationOriginHash, $value, $origin, $originVendor, $originUserId, $originVendorContext, $coordinates, $status, $confidence, $createdAt, $updatedAt, $asset, $definition, $translationOrigin, $translations];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$value, $locale, $locked, $origin, $originVendor, $originVendorContext];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$definition, $value, $locale, $locked, $origin, $originVendor, $originVendorContext];
        }

        return [];
    }
}
