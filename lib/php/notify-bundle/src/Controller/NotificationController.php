<?php

namespace Alchemy\NotifyBundle\Controller;

use Alchemy\NotifyBundle\Form\NotifyForm;
use Alchemy\NotifyBundle\Model\Notification;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/admin/notifications', name: 'alchemy_notify_admin_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotifierInterface $notifier,
        private EntityManagerInterface $em,
    )
    {
    }

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $workspaces = $this->em->getRepository(Workspace::class)->findAll();

        $form = $this->createForm(NotifyForm::class, null, [
            'workspaces' => $workspaces,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Notification $notification */
            $notification = $form->getData();
            $notificationId = 'basic';
            $payload = [
                'subject' => $notification->subject,
                'content' => $notification->content,
            ];

            if ($notification->topic) {
                $this->notifier->notifyTopic(
                    $notification->topic,
                    null,
                    $notificationId,
                    $payload
                );
            } else {
                $this->notifier->broadcast(
                    $notificationId,
                    $payload
                );
            }

            $request->getSession()->getFlashBag()->add('success', 'Notification sent!');

            return $this->redirect($request->getRequestUri());
        }

        return $this->render('@AlchemyNotify/admin/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
