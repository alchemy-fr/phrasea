<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\SavedSearchPrivacyEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class SavedSearchCrudController extends AbstractAclAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SavedSearch::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Saved Search')
            ->setEntityLabelInPlural('Saved Searches')
            ->setSearchFields(['id', 'name'])
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['name' => 'ASC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(ChoiceFilter::new('privacy')->setChoices(SavedSearchPrivacyEnum::getChoices()))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('updatedAt'))
            ->add($this->userChoiceFilter->createFilter('ownerId'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield TextField::new('name');
        yield ChoiceField::new('privacy')
            ->setChoices(SavedSearchPrivacyEnum::getChoices())
            ->setHelp('Secret: only the owner and granted users. Private: accessible by direct link but not listed. Public: listed to every user.')
        ;
        yield DateTimeField::new('updatedAt')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield JsonField::new('data')
        ->hideOnIndex();
    }
}
