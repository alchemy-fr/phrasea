<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Entity\Basket\Basket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BasketCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Basket::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield TextField::new('title');
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
    }
}
