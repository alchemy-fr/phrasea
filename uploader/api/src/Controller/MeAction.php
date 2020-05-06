<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Security\Voter\FormDataEditorVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MeAction extends AbstractController
{
    /**
     * @Route(path="/me", methods={"GET"})
     */
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        /** @var RemoteUser $user */
        $user = $this->getUser();

        return new JsonResponse([
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'permissions' => [
                'form_schema' => $this->isGranted(FormDataEditorVoter::EDIT_FORM_SCHEMA),
                'bulk_data' => $this->isGranted(FormDataEditorVoter::EDIT_BULK_DATA),
            ],
        ]);
    }
}
