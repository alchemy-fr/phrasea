<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField as BaseIdField;
use App\Entity\Expose\BasketPublication;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

class BasketPublicationCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return BasketPublication::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('instance');
        yield AssociationField::new('basket');
        yield BaseIdField::new('publicationId', 'Publication ID')
            ->setTemplatePath('@AlchemyAdmin/list/id.html.twig')
        ;
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
