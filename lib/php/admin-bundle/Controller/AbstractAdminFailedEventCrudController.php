<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\FailedEvent;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Model\FailedEventManager;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

abstract class AbstractAdminFailedEventCrudController extends AbstractAdminCrudController
{
    private EventProducer $eventProducer;
    private FailedEventManager $failedEventManager;
    private EntityManagerInterface $em;

    public static function getEntityFqcn(): string
    {
        return FailedEvent::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
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
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $createdAt, $type, $payload, $error];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$createdAt, $type, $error];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$createdAt, $type, $error];
        }

        return [];
    }

    protected function replayAction()
    {
        $id = $this->request->query->get('id');
        /** @var \Arthem\Bundle\RabbitBundle\Model\FailedEvent $failedEvent */
        $failedEvent = $this->getFailedEventRepository()->find($id);

        $this->getEventProducer()->publish(new EventMessage($failedEvent->getType(), $failedEvent->getPayload()));

        $this->addFlash('success', 'Message has been requeued');

        $this->em->remove($failedEvent);
        $this->em->flush();

        return $this->redirectToRoute('easyadmin',
            [
                'action' => 'list',
                'entity' => $this->request->query->get('entity'),
            ]);
    }

    protected function replayBatchAction(array $ids)
    {
        foreach ($ids as $id) {
            /** @var FailedEvent $failedEvent */
            $failedEvent = $this->failedEventManager->getRepository()->find($id);
            if ($failedEvent instanceof FailedEvent) {
                $this->eventProducer->publish(new EventMessage($failedEvent->getType(), $failedEvent->getPayload()));
                $this->em->remove($failedEvent);
            }
        }

        $this->em->flush();

        $this->addFlash('success', 'Messages have been requeued');
    }

    /** @required */
    public function setEventProducer(EventProducer $eventProducer): void
    {
        $this->eventProducer = $eventProducer;
    }

    /** @required */
    public function setFailedEventManager(FailedEventManager $failedEventManager): void
    {
        $this->failedEventManager = $failedEventManager;
    }

    /** @required */
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }
}
