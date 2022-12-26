<?php

namespace App\Controller\Admin;

use Alchemy\WebhookBundle\Entity\WebhookLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WebhookLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WebhookLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['id', 'event', 'payload', 'response'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $event = TextField::new('event');
        $response = TextareaField::new('response');
        $createdAt = DateTimeField::new('createdAt');
        $webhook = AssociationField::new('webhook');
        $id = Field::new('id', 'ID');
        $payload = TextField::new('payload');
        $webhookUrl = TextareaField::new('webhook.url', 'URL');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$webhookUrl, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $event, $payload, $response, $createdAt, $webhook];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$event, $response, $createdAt, $webhook];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$event, $response, $createdAt, $webhook];
        }
    }
}
