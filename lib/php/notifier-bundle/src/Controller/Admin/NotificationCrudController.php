<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\NotifierBundle\Entity\Notification;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class NotificationCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('In-app notification')
            ->setEntityLabelInPlural('In-app notifications')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
        ;
    }

    /**
     * Disabling the NEW action only hides the button: the route stays reachable
     * and EasyAdmin would call `new Notification()`, which needs a subscriber and
     * a topic. Notifications are produced by the notifier, never by hand.
     */
    public function createEntity(string $entityFqcn): never
    {
        throw new AccessDeniedHttpException('In-app notifications cannot be created from the admin.');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new(),
            AssociationField::new('subscriber', 'Recipient')->autocomplete(true),
            TextField::new('topic'),
            JsonField::new('payload')->hideOnIndex(),
            BooleanField::new('read')->renderAsSwitch(false),
            DateTimeField::new('readAt')->onlyOnDetail(),
            DateTimeField::new('createdAt')
            ->hideOnForm(),
        ];
    }
}
