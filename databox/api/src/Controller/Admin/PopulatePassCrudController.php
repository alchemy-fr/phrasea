<?php

namespace App\Controller\Admin;

use App\Entity\Admin\PopulatePass;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PopulatePassCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PopulatePass::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('PopulatePass')
            ->setEntityLabelInPlural('PopulatePass')
            ->setSearchFields(['id', 'documentCount', 'progress', 'indexName', 'mapping', 'error'])
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $endedAt = DateTimeField::new('endedAt');
        $documentCount = TextField::new('documentCount');
        $progress = TextField::new('progress');
        $indexName = TextField::new('indexName');
        $error = TextField::new('error');
        $createdAt = DateTimeField::new('createdAt');
        $id = Field::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $mapping = TextField::new('mapping');
        $progressString = TextareaField::new('progressString');
        $timeTakenUnit = TextareaField::new('timeTakenUnit');
        $successful = BooleanField::new('successful');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $indexName, $progressString, $timeTakenUnit, $endedAt, $successful, $error, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $endedAt, $documentCount, $progress, $indexName, $mapping, $error, $createdAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$endedAt, $documentCount, $progress, $indexName, $error, $createdAt];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$endedAt, $documentCount, $progress, $indexName, $error, $createdAt];
        }
    }
}
