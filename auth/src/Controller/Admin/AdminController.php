<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Consumer\Handler\PasswordChangedHandler;
use App\Consumer\Handler\UserInviteHandler;
use App\Entity\User;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    /**
     * @var UserManager
     */
    private $userManager;
    /**
     * @var EventProducer
     */
    private $eventProducer;

    public function __construct(UserManager $userManager, EventProducer $eventProducer)
    {
        $this->userManager = $userManager;
        $this->eventProducer = $eventProducer;
    }

    public function createNewUserEntity()
    {
        return $this->userManager->createUser();
    }

    protected function removeUserEntity($entity)
    {
        $this->userManager->removeUser($entity);
    }

    public function inviteAction()
    {
        $id = $this->request->query->get('id');
        /** @var User $entity */
        $entity = $this->em->getRepository(User::class)->find($id);

        if ($entity->isEmailVerified()) {
            $this->addFlash('warning', sprintf('User %s has already joined', $entity->getEmail()));
        } else {
            $this->eventProducer->publish(new EventMessage(UserInviteHandler::EVENT, [
                'id' => $entity->getId(),
            ]));

            $this->addFlash('success', sprintf('User will be invited by email at %s', $entity->getEmail()));
        }

        return $this->redirectToRoute('easyadmin', array(
            'action' => 'list',
            'entity' => $this->request->query->get('entity'),
        ));
    }
}
