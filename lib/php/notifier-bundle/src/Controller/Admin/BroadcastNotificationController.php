<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller\Admin;

use Alchemy\NotifierBundle\Form\BroadcastMessageType;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Model\BroadcastMessage;
use Alchemy\NotifierBundle\Model\BroadcastOptions;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Alchemy\NotifierBundle\Topic\BuiltInTopic;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Composes a free-form announcement and broadcasts it to an audience.
 *
 * Declared as an EasyAdmin route so the page is generated under each dashboard
 * (`/admin/notifications/broadcast`) and renders inside the admin layout.
 */
#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/notifications/broadcast', name: self::ROUTE_NAME, options: ['methods' => ['GET', 'POST']])]
final class BroadcastNotificationController extends AbstractController
{
    /**
     * Suffix of the generated route name, appended to the dashboard route name
     * (e.g. `easyadmin_notifier_broadcast`).
     */
    public const string ROUTE_NAME = 'notifier_broadcast';

    public function __construct(
        private readonly NotifierManager $notifier,
        private readonly UserDirectoryRegistry $directoryRegistry,
        private readonly Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $message = new BroadcastMessage();
        $message->directory = $this->directoryRegistry->getDefaultName();

        $form = $this->createForm(BroadcastMessageType::class, $message, [
            'directories' => $this->directoryRegistry->getChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->notifier->isEnabled()) {
                $this->addFlash('danger', 'Notifications are globally disabled (NOTIFICATIONS_ENABLED), nothing was sent.');

                return $this->redirect($request->getRequestUri());
            }

            $this->notifier->broadcast(
                BuiltInTopic::ADMIN_MESSAGE,
                $message->getParams(),
                new BroadcastOptions(
                    channels: $message->channels,
                    excludeUserId: $message->excludeMe ? $this->security->getUser()?->getUserIdentifier() : null,
                    directory: $message->directory,
                ),
            );

            $this->addFlash('success', sprintf(
                'Notification queued for "%s".',
                $this->directoryRegistry->get($message->directory)->getLabel(),
            ));

            return $this->redirect($request->getRequestUri());
        }

        return $this->render('@AlchemyNotifier/admin/broadcast.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
