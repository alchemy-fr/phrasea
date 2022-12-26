<?php

namespace App\Controller\Admin;

use App\Admin\Field\JsonField;
use App\Entity\FailedEvent;
use App\Form\Type\JsonCodeEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;

class FailedEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FailedEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('FailedEvent')
            ->setEntityLabelInPlural('FailedEvent')
            ->setSearchFields(['id', 'type', 'payload', 'error'])
            ->setPaginatorPageSize(200)
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            // todo: EA3
            //->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        $createdAt = DateTimeField::new('createdAt');
        $type = TextField::new('type')->setTemplatePath('@AlchemyAdmin/rabbit/type.html.twig');
        $error = TextareaField::new('error'); //->setTemplatePath('@ArthemRabbit/admin/error.html.twig');
        $id = Field::new('id', 'ID'); //->setTemplatePath('@ArthemRabbit/admin/id.html.twig');
        // todo: EA3 : cleanup tryout code
        // $payload = JsonCodeEditorType::new('payload');
        // $payload = CodeEditorField::new('payload')->setFormType(JsonCodeEditorType::class)->setLanguage('js'); //->setTemplatePath('@AlchemyAdmin/rabbit/payload.html.twig');
        $payload = JsonField::new('payloadAsJson', 'payload');
        // $payload = CodeEditorField::new('payloadAsJson')->setLanguage('js'); // ->setFormType(JsonCodeEditorType::class); //-; //->setTemplatePath('@AlchemyAdmin/rabbit/payload.html.twig');
        //$payload = TextareaField::new('payloadAsJson')->setTemplatePath('@AlchemyAdmin/rabbit/payload.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $type, $payload, $error, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $createdAt, $type, $payload, $error];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$createdAt, $type, $error];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$createdAt, $type, $error];
        }
    }
}
