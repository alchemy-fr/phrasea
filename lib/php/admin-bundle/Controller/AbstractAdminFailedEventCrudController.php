<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\FailedEvent;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

abstract class AbstractAdminFailedEventCrudController extends AbstractAdminCrudController
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;

    public static function getEntityFqcn(): string
    {
        return FailedEvent::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $replayAction = Action::new('replay', 'Replay')
            ->linkToCrudAction('replayEvent');

        return $actions
            ->add(Crud::PAGE_INDEX, $replayAction)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->addBatchAction(Action::new('replayAll', 'Replay all')
                ->linkToCrudAction('replayEvents')
                ->addCssClass('btn btn-primary')
            );
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('FailedEvent')
            ->setEntityLabelInPlural('FailedEvent')
            ->setSearchFields(['id', 'type', 'payload', 'error'])
            ->setPaginatorPageSize(200);
    }

    public function configureFields(string $pageName): iterable
    {
        $createdAt = DateTimeField::new('createdAt');
        $type = TextField::new('type')->setTemplatePath('@AlchemyAdmin/list/event_type.html.twig');
        $error = TextareaField::new('error')->setTemplatePath('@AlchemyAdmin/list/error.html.twig');
        $id = IdField::new();
        $payload = JsonField::new('payloadAsJson', 'Payload');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $type, $payload, $error, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $createdAt, $type, $payload, $error];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$createdAt, $type, $error];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$createdAt, $type, $error];
        }

        return [];
    }

    public function replayEvent(AdminContext $context)
    {
        $failedEvent = $context->getEntity()->getInstance();

        $this->eventProducer->publish(new EventMessage($failedEvent->getType(), $failedEvent->getPayload()));

        $this->addFlash('success', 'Message has been requeued');

        $this->em->remove($failedEvent);
        $this->em->flush();

        return $this->redirect($context->getReferrer());
    }

    public function replayEvents(BatchActionDto $batchActionDto)
    {
        $repo = $this->em->getRepository($batchActionDto->getEntityFqcn());

        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var FailedEvent $failedEvent */
            $failedEvent = $repo->find($id);
            if (!$failedEvent instanceof FailedEvent) {
                continue;
            }

            $this->eventProducer->publish(new EventMessage($failedEvent->getType(), $failedEvent->getPayload()));
            $this->em->remove($failedEvent);
        }

        $this->em->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setEventProducer(EventProducer $eventProducer): void
    {
        $this->eventProducer = $eventProducer;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }
}
