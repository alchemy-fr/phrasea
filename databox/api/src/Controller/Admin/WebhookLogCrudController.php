<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WebhookLogCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return WebhookLog::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'event', 'payload', 'response']);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new();
        $event = TextField::new('event');
        $response = TextareaField::new('response');
        $createdAt = DateTimeField::new('createdAt');
        $webhook = AssociationField::new('webhook');
        $payload = TextField::new('payload');
        $webhookUrl = TextareaField::new('webhook.url', 'URL');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$webhookUrl, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $event, $payload, $response, $createdAt, $webhook];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$event, $response, $createdAt, $webhook];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$event, $response, $createdAt, $webhook];
        }

        return [];
    }
}
