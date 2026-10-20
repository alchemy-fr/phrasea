<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Parade\Parade;
use App\Entity\Parade\ParadeLoginClient;
use App\Parade\ParadeLoginClientApplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ParadeLoginClientAction extends AbstractController
{
    private const string GROUPS_SCOPE_ROLE = 'ROLE_GROUPS';
    private const int MAX_ATTRIBUTES = 30;
    private const int MAX_VALUE_LENGTH = 4096;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParadeLoginClientApplier $applier,
    ) {
    }

    #[Route(path: '/parade-login-clients/{page}', requirements: ['page' => '_root|[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['PUT'])]
    public function __invoke(string $page, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if ($user instanceof JwtOauthClient) {
            $this->denyAccessUnlessGranted(self::GROUPS_SCOPE_ROLE);
        } elseif ($user instanceof JwtUser) {
            $this->denyAccessUnlessGranted(JwtUser::ROLE_ADMIN);
        } else {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }

        try {
            $data = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON body');
        }
        $baseClientId = is_array($data) ? ($data['baseClientId'] ?? null) : null;
        $attributes = is_array($data) ? ($data['attributes'] ?? null) : null;
        if (!is_string($baseClientId) || 1 !== preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $baseClientId) || str_contains($baseClientId, '--')) {
            throw new BadRequestHttpException('Invalid baseClientId');
        }
        if (!is_array($attributes) || count($attributes) > self::MAX_ATTRIBUTES) {
            throw new BadRequestHttpException('Invalid attributes');
        }
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || 1 !== preg_match('/^paradeLogin[A-Za-z]{1,40}$/', $key) || !is_string($value) || mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                throw new BadRequestHttpException(sprintf('Invalid attribute "%s"', (string) $key));
            }
        }

        $parade = $this->em->getRepository(Parade::class)->findOneBy([]);
        if (null === $parade || !$parade->isEnabled() || null === $parade->getUrl()) {
            throw new ConflictHttpException('Parade is not enabled with a public URL in the Databox admin.');
        }

        try {
            $record = $this->applier->apply($page, $baseClientId, $attributes, $parade->getUrl());
        } catch (\Throwable $e) {
            return new JsonResponse([
                'page' => $page,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'page' => $record->getPage(),
            'clientId' => $record->getClientId(),
            'status' => $record->getStatus(),
            'appliedAt' => $record->getAppliedAt()?->format(\DateTimeInterface::ATOM),
            'root' => ParadeLoginClient::ROOT_PAGE === $page,
        ]);
    }
}
