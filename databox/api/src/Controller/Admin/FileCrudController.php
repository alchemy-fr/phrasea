<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use App\Entity\Core\File;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
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

class FileCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return File::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('File')
            ->setEntityLabelInPlural('Files')
            ->setSearchFields(['id', 'type', 'checksum', 'docUniqueId', 'path', 'originalName', 'extension']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('workspace'))
            ->add(TextFilter::new('originalName'))
            ->add(TextFilter::new('type'))
            ->add(TextFilter::new('extension'))
            ->add(TextFilter::new('storage'))
            ->add(TextFilter::new('checksum'))
            ->add(AssociationIdentifierFilter::new('docUniqueId'))
            ->add(BooleanFilter::new('pathPublic'))
            ->add(BooleanFilter::new('importPending'))
            ->add(BooleanFilter::new('contentRejected'))
            ->add(NullFilter::new('accepted', 'Analysis')->setChoiceLabels('Pending', 'Done'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('originalName');
        yield TextField::new('path')
            ->hideOnIndex();
        yield TextField::new('storage');
        yield AssociationField::new('workspace')
            ->autocomplete();
        yield TextField::new('type');
        yield TextField::new('detectedType')
            ->onlyOnDetail();
        yield BooleanField::new('pathPublic')
            ->hideOnIndex();
        yield IntegerField::new('size')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        yield TextField::new('checksum')
            ->hideOnIndex();
        yield TextField::new('docUniqueId')
            ->hideOnIndex();
        yield TextField::new('extension')
            ->hideOnIndex();
        yield ArrayField::new('alternateUrls')
            ->hideOnIndex();
        yield JsonField::new('metadata')
            ->onlyOnDetail();
        yield DateTimeField::new('analyzedAt')
            ->onlyOnDetail();
        yield BooleanField::new('accepted')
            ->renderAsSwitch(false)
            ->onlyOnDetail();
        yield BooleanField::new('contentRejected')
            ->renderAsSwitch(false)
            ->onlyOnDetail();
        yield BooleanField::new('importPending')
            ->renderAsSwitch(false)
            ->onlyOnDetail();
        yield TextField::new('analysis?.status', 'Analysis status')
            ->onlyOnDetail();
        yield JsonField::new('analysis?.results', 'Analysis results')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();

    }
}
