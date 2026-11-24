<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\PublicationConfig;
use App\Entity\PublicationProfile;
use App\Field\PublicationConfigField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class PublicationProfileCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PublicationProfile::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('id'))
            ->add(TextFilter::new('name'))
            ->add($this->userChoiceFilter->createFilter('ownerId', 'Owner'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(BooleanFilter::new('config.enabled', 'Enabled'))
            ->add(BooleanFilter::new('config.publiclyListed', 'Publicly listed'))
            ->add(ChoiceFilter::new('config.securityMethod', 'Security method')->setChoices(PublicationConfig::getSecurityMethodChoices()))
            ->add(DateTimeFilter::new('config.beginsAt', 'Begins at'))
            ->add(DateTimeFilter::new('config.expiresAt', 'Expires at'))
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
        yield BooleanField::new('config.enabled', 'Enabled')
            ->renderAsSwitch(false)
            ->hideOnForm();

        yield TextField::new('config.layout', 'Layout')
            ->hideOnForm()
        ;
        yield BooleanField::new('config.publiclyListed', 'Publicly listed')
            ->renderAsSwitch(false)
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
    }
}
