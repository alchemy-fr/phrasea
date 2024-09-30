<?php

namespace App\Controller\Admin;

use App\Entity\Core\Workspace;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;

class WorkspaceCrudController extends AbstractAclAdminCrudController
{
    public function __construct(private readonly UserChoiceField $userChoiceField)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Workspace::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Workspace')
            ->setEntityLabelInPlural('Workspace')
            ->setSearchFields(['id', 'name', 'slug', 'ownerId', 'config', 'enabledLocales', 'localeFallbacks'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(BooleanFilter::new('public'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('name');
        yield TextField::new('slug');   
        yield TextField::new('ownerId')
            ->onlyOndetail();
        yield $this->userChoiceField->create('ownerId', 'Owner')
            ->onlyOnForms(); 
        yield ArrayField::new('enabledLocales');
        yield ArrayField::new('localeFallbacks');
        yield BooleanField::new('public')
            ->setHelp('If you need to expose a collection publicly, then its workspace has to be public.');  
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield DateTimeField::new('deletedAt')
            ->onlyOnDetail();
        yield AssociationField::new('collections')
            ->onlyOnDetail();
        yield AssociationField::new('tags')
            ->onlyOnDetail();
        yield AssociationField::new('renditionClasses')
            ->onlyOnDetail();
        yield AssociationField::new('renditionDefinitions')
            ->onlyOnDetail();
        yield AssociationField::new('renditionDefinitions')
            ->onlyOnDetail();
        yield AssociationField::new('attributeDefinitions')
            ->onlyOnDetail();    
        yield AssociationField::new('files')
        ->onlyOnDetail();
    }
}
