<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\Collection;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class CollectionCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly PrivacyField $privacyField,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Collection::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.storyAsset IS NULL');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Collection')
            ->setEntityLabelInPlural('Collections')
            ->setSearchFields(['id', 'title', 'ownerId', 'key', 'locale', 'privacy'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['workspace.name' => 'ASC', 'title' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('title'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('title');
        yield JsonField::new('translations')
            ->hideOnIndex();
        yield AssociationField::new('parent');
        yield AssociationField::new('storyAsset')
            ->hideOnForm();
        yield AssociationField::new('workspace');
        yield $this->privacyField->create('privacy');
        yield TextField::new('ownerId')
            ->onlyOnDetail();
        yield $this->userChoiceField->create('ownerId', 'Owner')
            ->onlyOnForms();
        yield TextField::new('key')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
        yield DateTimeField::new('deletedAt')
            ->onlyOnDetail();
        yield TextField::new('locale')
            ->onlyOnDetail();
        yield AssociationField::new('children')
            ->onlyOnDetail();
        yield AssociationField::new('assets')
            ->onlyOnDetail();
        yield AssociationField::new('referenceAssets')
            ->onlyOnDetail();
        yield JsonField::new('notificationSettings')
            ->hideOnIndex();

    }
}
