<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\Broadcast;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Broadcast history, and the form to send a new one.
 *
 * "Create" does not persist the entity itself: it hands it to the notifier,
 * which records it and lets the worker walk the audience. Existing rows are
 * read-only — a broadcast that has been sent cannot be edited away.
 */
#[IsGranted('ROLE_ADMIN')]
class BroadcastCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly NotifierManager $notifier,
        private readonly UserDirectoryRegistry $directoryRegistry,
        private readonly UserChoiceField $userChoiceField,
        private readonly UserChoiceFilter $userChoiceFilter,
        private readonly Security $security,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Broadcast::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Broadcast')
            ->setEntityLabelInPlural('Broadcasts')
            ->setPageTitle(Crud::PAGE_NEW, 'Broadcast a notification')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::EDIT, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a): Action => $a->setLabel('Broadcast a notification')->setIcon('fas fa-bullhorn'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, static fn (Action $a): Action => $a->setLabel('Send'))
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('topic'))
            ->add(TextFilter::new('directory'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add($this->userChoiceFilter->createFilter('initiatorUserId', 'Sent by'))
        ;
    }

    public function createEntity(string $entityFqcn): Broadcast
    {
        // The admin composes free-form announcements; other topics are
        // broadcast from the code or the CLI.
        return new Broadcast(directory: $this->directoryRegistry->getDefaultName());
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Broadcast) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        $initiatorUserId = $this->security->getUser()?->getUserIdentifier();
        $entityInstance->setInitiatorUserId($initiatorUserId);
        $entityInstance->setExcludeUserId($entityInstance->isExcludeInitiator() ? $initiatorUserId : null);

        // Recording the row is the notifier's job: it must stay in step with
        // the message it dispatches.
        if (!$this->notifier->dispatchBroadcast($entityInstance)) {
            $this->addFlash('danger', 'Notifications are globally disabled (NOTIFICATIONS_ENABLED), nothing was sent.');

            return;
        }

        $this->addFlash('success', sprintf(
            'Notification queued for "%s".',
            $this->directoryRegistry->get($entityInstance->getDirectory())->getLabel(),
        ));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()->hideOnForm();
        yield DateTimeField::new('createdAt', 'Sent at')->hideOnForm();
        yield $this->userChoiceField->create('initiatorUserId', 'Sent by')->hideOnForm();

        yield TextField::new('subject')
            ->setColumns(12)
            ->setFormTypeOption('constraints', [new NotBlank()])
            ->hideOnIndex();
        yield TextareaField::new('body', 'Message')
            ->setColumns(12)
            ->setNumOfRows(8)
            ->setHelp('HTML is allowed (emails and in-app notifications render it as-is).')
            ->setFormTypeOption('constraints', [new NotBlank()])
            ->hideOnIndex();
        yield TextField::new('url', 'Link')
            ->setRequired(false)
            ->setHelp('Client URI the notification points at, e.g. /assets/42.')
            ->hideOnIndex();

        yield TextField::new('topic')->hideOnForm();

        yield ChoiceField::new('channels')
            ->setChoices($this->channelChoices())
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setFormTypeOption('constraints', [new Count(min: 1, minMessage: 'Pick at least one channel.')])
            ->onlyOnForms();
        yield TextField::new('channelLabels', 'Channels')->hideOnForm();

        yield ChoiceField::new('directory', 'Audience')
            ->setChoices(array_flip($this->directoryRegistry->getChoices()))
            ->setHelp('Who receives this notification.');

        yield BooleanField::new('excludeInitiator', 'Do not notify me')
            ->renderAsSwitch(false)
            ->onlyOnForms();

        yield IntegerField::new('deliveredCount', 'Delivered')->hideOnForm();
        yield IntegerField::new('failedCount', 'Failed')->hideOnForm();
        yield DateTimeField::new('startedAt')->onlyOnDetail();
        yield DateTimeField::new('completedAt')->onlyOnDetail();
        yield $this->userChoiceField->create('excludeUserId', 'Excluded user')->onlyOnDetail();
        yield JsonField::new('payload')->onlyOnDetail();
    }

    /**
     * @return array<string, string> label => channel value
     */
    private function channelChoices(): array
    {
        $choices = [];
        foreach (ChannelType::cases() as $channel) {
            $choices[$channel->label()] = $channel->value;
        }

        return $choices;
    }
}
