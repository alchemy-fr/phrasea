<?php

declare(strict_types=1);

namespace App\Admin;

use App\Consumer\Handler\Notify\RegisterUserToNotifierHandler;
use App\Consumer\Handler\UserInviteHandler;
use App\Entity\User;
use App\Form\ImportUsersForm;
use App\Form\RoleChoiceType;
use App\User\Import\UserImporter;
use App\User\InviteManager;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminController extends EasyAdminController
{
    private UserManager $userManager;
    private EventProducer $eventProducer;
    private UserImporter $userImporter;
    private InviteManager $inviteManager;

    public function __construct(
        UserManager $userManager,
        EventProducer $eventProducer,
        UserImporter $userImporter,
        InviteManager $inviteManager
    ) {
        $this->userManager = $userManager;
        $this->eventProducer = $eventProducer;
        $this->userImporter = $userImporter;
        $this->inviteManager = $inviteManager;
    }

    public function createNewUserEntity()
    {
        return $this->userManager->createUser();
    }

    /**
     * @param User $entity
     */
    public function persistUserEntity($entity)
    {
        $this->persistEntity($entity);

        if ($entity->isInviteByEmail()) {
            $this->eventProducer->publish(new EventMessage(UserInviteHandler::EVENT, [
                'id' => $entity->getId(),
            ]));
        } else {
            $this->eventProducer->publish(new EventMessage(RegisterUserToNotifierHandler::EVENT, [
                'id' => $entity->getId(),
            ]));
        }
    }

    protected function createUserEntityFormBuilder($entity, $view)
    {
        $formBuilder = $this->createEntityFormBuilder($entity, $view);

        if ($entity === $this->getUser()) {
            $formBuilder->remove('roles');
            $formBuilder->add('roles', RoleChoiceType::class, [
                'disabled' => true,
            ]);
        }

        return $formBuilder;
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
            $this->addFlash('warning', sprintf('User %s has already joined', $entity->getUsername()));
        } elseif (!$this->inviteManager->userCanBeInvited($entity)) {
            $this->addFlash('warning', sprintf(
                'User %s has already been invited less than %d seconds ago',
                $entity->getUsername(),
                $this->inviteManager->getAllowedInviteDelay()
            ));
        } else {
            $this->eventProducer->publish(new EventMessage(UserInviteHandler::EVENT, [
                'id' => $entity->getId(),
            ]));

            $this->addFlash('success', sprintf('User will be invited by email at %s', $entity->getEmail()));
        }

        return $this->redirectToRoute('easyadmin', [
            'action' => 'list',
            'entity' => $this->request->query->get('entity'),
        ]);
    }

    public function importAction()
    {
        $request = $this->request;
        $form = $this->createForm(ImportUsersForm::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $fileField = $form->get('file');
            $inviteUsers = (bool) $form->get('invite')->getData();
            /** @var UploadedFile $file */
            $file = $fileField->getData();
            $violations = [];
            $count = $this->userImporter->import(fopen($file->getRealPath(), 'r'), $inviteUsers, $violations);

            if (!empty($violations)) {
                $limit = 0;
                $maxErrors = 10;
                foreach ($violations as $violation) {
                    if ($limit++ >= $maxErrors) {
                        $fileField->addError(new FormError(sprintf('You have more than %d errors', $maxErrors)));
                        break;
                    }
                    $fileField->addError(new FormError($violation));
                }
            } else {
                $this->addFlash('success', sprintf('%d users have been imported', $count));

                return $this->redirectToReferrer();
            }
        }

        return $this->render('admin/User/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
