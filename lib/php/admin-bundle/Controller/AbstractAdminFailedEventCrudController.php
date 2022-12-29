<?php

namespace Alchemy\AdminBundle\Controller;

use App\Entity\FailedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

abstract class AbstractAdminFailedEventCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return FailedEvent::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('FailedEvent')
            ->setEntityLabelInPlural('FailedEvent')
            ->setSearchFields(['id', 'type', 'payload', 'error'])
            ->setPaginatorPageSize(200);
    }

    public function configureFields(string $pageName): iterable
    {
        $createdAt = DateTimeField::new('createdAt');
        $type = TextField::new('type')->setTemplatePath('@ArthemRabbit/admin/type.html.twig');
        $error = TextareaField::new('error')->setTemplatePath('@ArthemRabbit/admin/error.html.twig');
        // todo: EA3 ; restore copy payload
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig'); //->setTemplatePath('@ArthemRabbit/admin/id.html.twig');
        $payload = TextField::new('payload')->setTemplatePath('@ArthemRabbit/admin/payload.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $type, $payload, $error, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $createdAt, $type, $payload, $error];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$createdAt, $type, $error];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$createdAt, $type, $error];
        }

        return [];
    }
}
