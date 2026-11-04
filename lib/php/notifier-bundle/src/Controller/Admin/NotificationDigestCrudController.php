<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\NotifierBundle\Entity\NotificationDigest;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pending notification digests: the buffers of events waiting for their quiet
 * period before being sent as one grouped notification.
 *
 * Rows are transient (created by the first buffered event, removed by the
 * flush), so this listing shows the digests currently open. Deleting a row
 * discards its buffered events without sending anything — the in-flight flush
 * probe then finds nothing and stops.
 */
#[IsGranted('ROLE_ADMIN')]
class NotificationDigestCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationDigest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Pending digest')
            ->setEntityLabelInPlural('Pending digests')
            ->setDefaultSort(['lastEventAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('topic'))
            ->add(TextFilter::new('channel'))
            ->add(DateTimeFilter::new('firstEventAt'))
            ->add(DateTimeFilter::new('lastEventAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new(),
            AssociationField::new('subscriber', 'Recipient')->autocomplete(true),
            TextField::new('topic'),
            TextField::new('channel'),
            IntegerField::new('eventCount', 'Events'),
            DateTimeField::new('firstEventAt', 'First event'),
            DateTimeField::new('lastEventAt', 'Last event'),
            DateTimeField::new('createdAt')->onlyOnDetail(),
            JsonField::new('events')->onlyOnDetail(),
        ];
    }
}
