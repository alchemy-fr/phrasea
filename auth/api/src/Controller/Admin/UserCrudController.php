<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use App\Consumer\Handler\UserInviteHandler;
use App\Entity\User;
use App\Form\ImportUsersForm;
use App\User\Import\UserImporter;
use App\User\InviteManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;


class UserCrudController extends AbstractAdminCrudController
{

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    private AdminUrlGenerator $adminUrlGenerator;
    private RequestStack $requestStack;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, RequestStack $requestStack)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->requestStack = $requestStack;
    }

    public function configureActions(Actions $actions): Actions
    {
        $importAction = Action::new('import')
            ->createAsGlobalAction()
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();
                return $this->adminUrlGenerator->setAll($request->query->all())
                    ->setAction('importAction')
                    ->generateUrl();
            })
        ;

        $inviteAction = Action::new('invite')
            ->linkToCrudAction('inviteAction');

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $importAction)
            ->add(Crud::PAGE_INDEX, $inviteAction);
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('User')
            ->setSearchFields(['username']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('enabled');
    }

    public function configureFields(string $pageName): iterable
    {
        $username = TextField::new('username');
        $userRoles = ArrayField::new('userRoles');
        $enabled = Field::new('enabled');
        $groups = AssociationField::new('groups');
        $inviteByEmail = Field::new('inviteByEmail');
        $id = IdField::new('id', 'ID')->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
        $emailVerified = Field::new('emailVerified');
        $securityToken = TextField::new('securityToken');
        $salt = TextField::new('salt');
        $roles = TextField::new('roles');
        $password = TextField::new('password');
        $locale = TextField::new('locale');
        $createdAt = DateTimeField::new('createdAt');
        $lastInviteAt = DateTimeField::new('lastInviteAt');
        $updatedAt = DateTimeField::new('updatedAt');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $username, $enabled, $groups, $userRoles, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $username, $emailVerified, $enabled, $securityToken, $salt, $roles, $password, $locale, $createdAt, $lastInviteAt, $updatedAt, $groups];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$username, $userRoles, $enabled, $groups, $inviteByEmail];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$username, $userRoles, $enabled, $groups];
        }

        return [];
    }

    public function inviteAction(AdminContext $adminContext, InviteManager $inviteManager, EventProducer $eventProducer)
    {
        $user = $adminContext->getEntity()->getInstance();
        if (!$user instanceof User) {
            throw new \LogicException('Entity is missing or not a Commit');
        }

        $request = $adminContext->getRequest();

        if ($user->isEmailVerified()) {
            $this->addFlash('warning', sprintf('User %s has already joined', $user->getUsername()));
        } elseif (!$inviteManager->userCanBeInvited($user)) {
            $this->addFlash('warning', sprintf(
                'User %s has already been invited less than %d seconds ago',
                $user->getUsername(),
                $inviteManager->getAllowedInviteDelay()
            ));
        } else {
            $eventProducer->publish(new EventMessage(UserInviteHandler::EVENT, [
                'id' => $user->getId(),
            ]));

            $this->addFlash('success', sprintf('User will be invited by email at %s', $user->getEmail()));
        }

//        return $this->redirectToRoute('easyadmin', [
//            'action' => 'list',
//            'entity' => $this->request->query->get('entity'),
//        ]);
//
        $targetUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->setEntityId($user->getId())
            ->generateUrl();

        return $this->redirect($targetUrl);
    }

    public function importAction(AdminContext $adminContext, UserImporter $userImporter)
    {
        $request = $adminContext->getRequest();
        $form = $this->createForm(ImportUsersForm::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $fileField = $form->get('file');
            $inviteUsers = (bool) $form->get('invite')->getData();
            /** @var UploadedFile $file */
            $file = $fileField->getData();
            $violations = [];
            $count = $userImporter->import(fopen($file->getRealPath(), 'r'), $inviteUsers, $violations);

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

                // return $this->redirectToReferrer();

                $targetUrl = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Crud::PAGE_INDEX)
                    ->generateUrl();

                return $this->redirect($targetUrl);
            }
        }

        return $this->render('admin/User/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }

}
