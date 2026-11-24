<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Admin\Field\PrivacyField;
use App\Entity\Core\AssetImport;
use App\Model\AssetImportStatusEnum;
use App\Service\Import\AssetImportManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;

class AssetImportCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
        private readonly PrivacyField $privacyField,
        private readonly AssetImportManager $importManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AssetImport::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Asset Import')
            ->setEntityLabelInPlural('Asset Imports')
            ->setSearchFields(['id', 'sourcePath', 'originalName'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(AssociationIdentifierFilter::new('collection'))
            ->add(ChoiceFilter::new('status')->setChoices(AssetImportStatusEnum::getChoices()))
            ->add(ChoiceFilter::new('format')->setChoices(self::getFormatChoices()))
            ->add($this->userChoiceFilter->createFilter('ownerId', 'Owner'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('endedAt'))
            ->add(NullFilter::new('error')->setChoiceLabels('No error', 'Error'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield AssociationField::new('workspace');
        yield AssociationField::new('collection');
        yield AssociationField::new('integration')
            ->hideOnIndex();
        yield ChoiceField::new('format')
            ->setChoices(self::getFormatChoices());
        yield ChoiceField::new('status')
            ->setChoices(AssetImportStatusEnum::getChoices());
        yield $this->privacyField->create('privacy')
            ->hideOnIndex();
        yield TextField::new('sourcePath')
            ->hideOnIndex();
        yield TextField::new('originalName')
            ->hideOnIndex();
        yield IntegerField::new('total');
        yield IntegerField::new('created');
        yield IntegerField::new('skipped');
        yield IntegerField::new('failed');
        yield TextareaField::new('error')
            ->hideOnIndex();
        yield $this->userChoiceField->create('ownerId', 'Owner');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('endedAt');
        yield DateTimeField::new('updatedAt')
            ->hideOnIndex();
    }

    /**
     * Like the API deletion: the import tracking and its instructions file go, never the created assets.
     *
     * @param AssetImport $entityInstance
     */
    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->importManager->scheduleFileDeletion($entityInstance);

        parent::deleteEntity($entityManager, $entityInstance);
    }

    /**
     * @return array<string, string>
     */
    private static function getFormatChoices(): array
    {
        return array_combine(AssetImport::FORMATS, AssetImport::FORMATS);
    }
}
