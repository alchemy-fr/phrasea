<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\RegistrationHandler;
use App\Form\RegisterForm;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(name="app_")
 */
class DefaultController extends AbstractController
{
    /**
     * @Route(path="/", name="index")
     */
    public function indexAction()
    {
        throw new NotFoundHttpException();
    }
}
