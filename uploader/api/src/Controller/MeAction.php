<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Security\Voter\FormDataEditorVoter;
use App\Security\Voter\UploadLinkVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MeAction extends AbstractController
{
    #[Route(path: '/me', methods: ['GET'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        #[Autowire(param: 'app.upload_link.max_ttl')]
        int $uploadLinkMaxTtl,
    ): Response {
        /** @var JwtUser $user */
        $user = $this->getUser();

        return new JsonResponse([
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'permissions' => [
                'form_schema' => $this->isGranted(FormDataEditorVoter::EDIT_FORM_SCHEMA),
                'target_data' => $this->isGranted(FormDataEditorVoter::EDIT_TARGET_DATA),
                'upload_link' => $this->isGranted(UploadLinkVoter::CREATE),
            ],
            // In days: how far the expiration of an upload link can be set
            'upload_link_max_ttl' => $uploadLinkMaxTtl,
        ]);
    }
}
