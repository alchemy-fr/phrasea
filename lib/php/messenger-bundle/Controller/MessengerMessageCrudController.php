<?php

namespace Alchemy\MessengerBundle\Controller;

use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\SuperAdminVoter;
use Alchemy\MessengerBundle\Entity\MessengerMessage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("'.SuperAdminVoter::ROLE.'") or is_granted("'.JwtUser::ROLE_TECH.'")'))]
class MessengerMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SerializerInterface $messengerSerializer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function configureActions(Actions $actions): Actions
    {
        $retry = Action::new('retry', 'Retry', 'fas fa-refresh')
            ->displayIf(fn (MessengerMessage $entity) => !$entity->wasRetried())
            ->linkToCrudAction('retry');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, $retry)
            ->add(Crud::PAGE_DETAIL, $retry)
        ;
    }

    public function retry(AdminContext $context): RedirectResponse
    {
        /** @var MessengerMessage $message */
        $message = $context->getEntity()->getInstance();
        $envelope = $this->messengerSerializer->decode([
            'body' => $message->getBody(),
            'headers' => $message->getDecodedHeaders(),
        ]);

        $this->bus->dispatch($envelope
            ->withoutAll(DelayStamp::class)
            ->withoutAll(RedeliveryStamp::class)
            ->withoutAll(SentToFailureTransportStamp::class)
            ->withoutAll(ErrorDetailsStamp::class));

        $message->setDeliveredAt(new \DateTimeImmutable('9999-12-31 23:59:59'));
        $this->em->persist($message);
        $this->em->flush();

        return new RedirectResponse($context->getReferrer());
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public static function getEntityFqcn(): string
    {
        return MessengerMessage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new(),
            DateTimeField::new('createdAt'),
            JsonField::new('body')
                ->hideOnIndex(),
            CodeField::new('type'),
            TextField::new('error'),
            JsonField::new('headers')
                ->hideOnIndex(),
            DateTimeField::new('deliveredAt'),
            DateTimeField::new('availableAt'),
        ];
    }
}
