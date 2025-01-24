<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\PublicationProfile;
use App\Field\PublicationConfigField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class PublicationProfileCrudController extends AbstractAclAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return PublicationProfile::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('id'))
            ->add(TextFilter::new('name'))
            ->add(TextFilter::new('ownerId'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield IdField::new('ownerId');

        yield PublicationConfigField::new('config')
            ->hideOnIndex()
            ->hideOnDetail();
        yield Field::new('config.enabled', 'Enabled')
            ->hideOnForm();

        yield TextField::new('config.layout', 'Layout')
            ->hideOnForm()
        ;
        yield Field::new('config.publiclyListed', 'PubliclyListed')
            ->hideOnForm();
        yield DateTimeField::new('config.beginsAt', 'Begins At')
            ->hideOnForm();
        yield DateTimeField::new('config.expiresAt', 'Expires At')
            ->hideOnForm();

        yield TextField::new('config.securityMethod', 'Security Method')
            ->hideOnForm();

        yield TextareaField::new('clientAnnotations')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createdAt')
            ->hideOnForm()
        ;

        return [];
    }
}
