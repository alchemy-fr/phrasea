<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use App\Entity\UploadLink;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * The token of a link is never shown: it is the secret granting the upload.
 *
 * Links are created from the Uploader client only, which records the groups and roles of their creator.
 */
class UploadLinkCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UploadLink::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Upload Link')
            ->setEntityLabelInPlural('Upload Links')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'name', 'ownerId']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(EntityFilter::new('target'))
            ->add(BooleanFilter::new('enabled'))
            ->add($this->userChoiceFilter->createFilter('ownerId', 'Owner'))
            ->add(DateTimeFilter::new('expiresAt'))
            ->add(DateTimeFilter::new('lastCommitAt'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnIndex();
        yield TextField::new('name');
        yield AssociationField::new('target')
            ->hideOnForm();
        yield ChoiceField::new('status')
            ->setChoices([
                'Active' => UploadLink::STATUS_ACTIVE,
                'Disabled' => UploadLink::STATUS_DISABLED,
                'Expired' => UploadLink::STATUS_EXPIRED,
                'Exhausted' => UploadLink::STATUS_EXHAUSTED,
            ])
            ->renderAsBadges([
                UploadLink::STATUS_ACTIVE => 'success',
                UploadLink::STATUS_DISABLED => 'secondary',
                UploadLink::STATUS_EXPIRED => 'warning',
                UploadLink::STATUS_EXHAUSTED => 'danger',
            ])
            ->setSortable(false)
            ->hideOnForm();
        yield BooleanField::new('enabled')
            ->renderAsSwitch(false);
        yield DateTimeField::new('expiresAt');
        yield IntegerField::new('usedAssets', 'Assets')
            ->formatValue(static fn (int $usedAssets, UploadLink $link): string => sprintf('%d / %s', $usedAssets, $link->getMaxAssets() ?? '∞'))
            ->hideOnForm();
        yield IntegerField::new('usedSize', 'Used size')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig')
            ->onlyOnDetail();
        yield IntegerField::new('maxTotalSize', 'Max total size')
            ->setTemplatePath('@AlchemyAdmin/list/file_size.html.twig')
            ->onlyOnDetail();
        yield $this->userChoiceField->create('ownerId', 'Owner')
            ->hideOnForm();
        yield JsonField::new('creatorGroups')
            ->onlyOnDetail();
        yield JsonField::new('creatorRoles')
            ->onlyOnDetail();
        yield DateTimeField::new('lastCommitAt')
            ->hideOnForm();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt')
            ->onlyOnDetail();
    }
}
