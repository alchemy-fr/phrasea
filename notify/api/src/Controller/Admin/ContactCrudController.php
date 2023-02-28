<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\Contact;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ContactCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contact::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Contact')
            ->setEntityLabelInPlural('Contact')
            ->setSearchFields(['id', 'userId', 'email', 'phone', 'locale']);
    }

    public function configureFields(string $pageName): iterable
    {
        $userId = IdField::new('userId');
        $email = TextField::new('email');
        $phone = TextField::new('phone');
        $id = IdField::new();
        $locale = TextField::new('locale');
        $createdAt = DateTimeField::new('createdAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $userId, $email, $phone, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $userId, $email, $phone, $locale, $createdAt];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$userId, $email, $phone];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$userId, $email, $phone];
        }

        return [];
    }
}
