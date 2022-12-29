<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Entity\Commit;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CommitCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Commit::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Commit')
            ->setEntityLabelInPlural('Commit')
            ->setSearchFields(['id', 'totalSize', 'formData', 'options', 'userId', 'token', 'notifyEmail', 'locale'])
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $userId = TextField::new('userId')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $token = TextField::new('token');
        $acknowledged = BooleanField::new('acknowledged');
        $formDataJson = Field::new('formDataJson');
        $optionsJson = Field::new('optionsJson');
        $notifyEmail = TextField::new('notifyEmail');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $totalSize = IntegerField::new('totalSize')->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        $formData = TextField::new('formData');
        $options = TextField::new('options');
        $locale = TextField::new('locale');
        $acknowledgedAt = DateTimeField::new('acknowledgedAt');
        $createdAt = DateTimeField::new('createdAt');
        $assets = AssociationField::new('assets');
        $target = AssociationField::new('target');
        $assetCount = IntegerField::new('assetCount');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $target, $userId, $assetCount, $token, $acknowledged, $totalSize, $notifyEmail, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $totalSize, $formData, $options, $userId, $token, $acknowledged, $notifyEmail, $locale, $acknowledgedAt, $createdAt, $assets, $target];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$userId, $token, $acknowledged, $formDataJson, $optionsJson, $notifyEmail];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$userId, $token, $acknowledged, $formDataJson, $optionsJson, $notifyEmail];
        }
        return [];
    }
}
