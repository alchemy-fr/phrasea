<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Consumer\Handler\UserInviteHandler;
use App\Entity\User;
use App\Form\ImportUsersForm;
use App\Form\RoleChoiceType;
use App\User\Import\UserImporter;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

    /**
     * @var UserImporter
     */
    private $userImporter;

    public function __construct(UserManager $userManager, EventProducer $eventProducer, UserImporter $userImporter)
    {
        $this->userManager = $userManager;
        $this->eventProducer = $eventProducer;
        $this->userImporter = $userImporter;
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
