<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\Asset;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class AssetCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Asset::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    /**
     * @param Asset $entityInstance
     */
    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance->isCommitted()) {
            parent::deleteEntity($entityManager, $entityInstance);

            return;
        }

        $this->assetManager->removeUncommitted($entityInstance);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPaginatorPageSize(100)
            ->setEntityLabelInSingular('Asset')
            ->setEntityLabelInPlural('Assets')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'path', 'size', 'originalName', 'mimeType', 'userId']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('id'))
            ->add($this->userChoiceFilter->createFilter('userId', 'User'))
            ->add(EntityFilter::new('target'))
            ->add(TextFilter::new('originalName'))
            ->add(TextFilter::new('mimeType'))
            ->add(BooleanFilter::new('acknowledged'))
            ->add(AssociationIdentifierFilter::new('commit'))
            ->add(NullFilter::new('commit.id', 'Committed')->setChoiceLabels('Not committed', 'Committed'))
            ->add(AssociationIdentifierFilter::new('uploadLink'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield IdField::new('userId');
        yield IntegerField::new('size')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        yield TextField::new('originalName');
        yield TextField::new('mimeType');
        yield TextField::new('path')
            ->hideOnIndex();
        yield JsonField::new('formData')
            ->hideOnIndex();
        yield JsonField::new('data')
            ->hideOnIndex();
        yield BooleanField::new('acknowledged')->renderAsSwitch(false);
        yield AssociationField::new('target')
            ->autocomplete();
        yield AssociationField::new('commit')
            ->autocomplete();
        yield TextField::new('uploadLink.name', 'Upload link')
            ->hideOnForm();
        yield BooleanField::new('committed')->renderAsSwitch(false);
        yield DateTimeField::new('createdAt');
    }
}
