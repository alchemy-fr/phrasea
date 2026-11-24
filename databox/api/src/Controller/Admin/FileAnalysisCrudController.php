<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Core\File;
use App\Entity\Core\FileAnalysis;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class FileAnalysisCrudController extends AbstractAdminCrudController
{
    private const array STATUS_CHOICES = [
        'Success' => File::ANALYSIS_SUCCESS,
        'Failed' => File::ANALYSIS_FAILED,
        'Skipped' => File::ANALYSIS_SKIPPED,
        'Bypassed' => File::ANALYSIS_BYPASSED,
    ];

    public static function getEntityFqcn(): string
    {
        return FileAnalysis::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('File Analysis')
            ->setEntityLabelInPlural('File Analyses')
            ->setSearchFields(['id', 'hash'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(self::STATUS_CHOICES))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield ChoiceField::new('status')
            ->setChoices(self::STATUS_CHOICES);
        yield TextField::new('hash');
        yield TextareaField::new('message')
            ->onlyOnDetail();
        yield JsonField::new('results')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt');
    }
}
