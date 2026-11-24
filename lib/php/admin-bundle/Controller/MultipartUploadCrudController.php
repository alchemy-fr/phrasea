<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class MultipartUploadCrudController extends AbstractAdminCrudController
{
    public function __construct(private readonly UserChoiceFilter $userChoiceFilter)
    {
    }

    public static function getEntityFqcn(): string
    {
        return MultipartUpload::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Multipart Upload')
            ->setEntityLabelInPlural('Multipart Uploads');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(AssociationIdentifierFilter::new('id'))
            ->add(TextFilter::new('type'))
            ->add(TextFilter::new('filename'))
            ->add($this->userChoiceFilter->createFilter('ownerId', 'Owner'))
            ->add(BooleanFilter::new('complete'))
            ->add(NullFilter::new('consumedAt', 'Consumed')->setChoiceLabels('Not consumed', 'Consumed'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('type');
        yield IdField::new('uploadId')->hideOnIndex();
        yield TextField::new('filename');
        yield TextField::new('ownerId');
        yield IntegerField::new('sizeAsString', 'Size')
            ->formatValue(static fn ($value, MultipartUpload $upload): int => $upload->getSize())
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig');
        yield TextField::new('path')->hideOnIndex();
        yield BooleanField::new('complete')->renderAsSwitch(false);
        yield DateTimeField::new('consumedAt');
        yield DateTimeField::new('createdAt');

        return [];
    }
}
