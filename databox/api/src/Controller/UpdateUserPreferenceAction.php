<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\UserPreference;
use App\Service\User\UserPreferencesManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UpdateUserPreferenceAction extends AbstractController
{
    #[Route(path: '/preferences', methods: ['GET'])]
    public function getUserPreferences(UserPreferencesManager $userPreferencesManager): Response
    {
        $user = $this->getRemoteUser();
        $pref = $userPreferencesManager->getPreferences($user->getId());

        return $this->createResponse($pref);
    }

    #[Route(path: '/preferences', methods: ['PUT'])]
    public function updateUserPreferences(Request $request, UserPreferencesManager $userPreferencesManager): Response
    {
        $user = $this->getRemoteUser();

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (empty($name = ($data['name'] ?? null))) {
            throw new BadRequestHttpException('Missing or empty name');
        }
        $value = $data['value'] ?? null;

        $pref = $userPreferencesManager->updatePreferences(
            $user->getId(),
            $name,
            $value
        );

        return $this->createResponse($pref);
    }

    private function createResponse(UserPreference $preferences): Response
    {
        if (empty($preferences->getData())) {
            return new Response('{}', 200, [
                'Content-Type' => 'application/json',
            ]);
        }

        return new JsonResponse($preferences->getData());
    }

    private function getRemoteUser(): JwtUser
    {
        /** @var JwtUser $user */
        $user = $this->getUser();

        if (!$user instanceof JwtUser) {
            throw new UnauthorizedHttpException(sprintf('Invalid user "%s"', get_debug_type($user)));
        }

        return $user;
    }
}
