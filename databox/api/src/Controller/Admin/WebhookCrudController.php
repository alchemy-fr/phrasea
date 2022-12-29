<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\WebhookBundle\Entity\Webhook;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WebhookCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Webhook::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setSearchFields(['id', 'url', 'secret', 'events', 'options'])
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $url = TextField::new('url', 'URL');
        $events = TextField::new('events');
        $verifySSL = Field::new('verifySSL');
        $secret = TextField::new('secret');
        $timeout = Field::new('timeout');
        $active = Field::new('active');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $options = TextField::new('options');
        $createdAt = DateTimeField::new('createdAt');
        $eventsLabel = TextareaField::new('eventsLabel');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$url, $active, $eventsLabel, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $url, $secret, $verifySSL, $active, $events, $options, $createdAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$url, $events, $verifySSL, $secret, $timeout, $active];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$url, $events, $verifySSL, $secret, $timeout, $active];
        }
        return [];
    }
}
