<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use Alchemy\NotifierBundle\Entity\Subscriber;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SubscriberCrudController extends AbstractAdminCrudController
{
    public function __construct(private readonly UserChoiceFilter $userChoiceFilter)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Subscriber::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Subscriber')
            ->setEntityLabelInPlural('Subscribers')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add($this->userChoiceFilter->createFilter('userId', 'User'))
            ->add(TextFilter::new('email'))
            ->add(TextFilter::new('displayName'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new(),
            TextField::new('userId', 'User ID'),
            TextField::new('displayName'),
            TextField::new('email'),
            TextField::new('phoneNumber'),
            TextField::new('locale'),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->onlyOnDetail(),
        ];
    }
}
