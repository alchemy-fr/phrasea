<?php

declare(strict_types=1);

namespace App\Controller\Admin;

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
}
