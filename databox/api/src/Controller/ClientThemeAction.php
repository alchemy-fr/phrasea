<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntryRepository;
use Alchemy\ConfiguratorBundle\Message\DeployConfig;
use App\Config\Schema\DataboxConfigSchema;
use App\Service\Admin\ClientThemeNormalizer;
use App\Service\Admin\InvalidClientThemeException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The organisation theme of the client: a palette and a few style properties
 * customized by an administrator. It is stored as the `databox.theme`
 * configurator entry (see DataboxConfigSchema) and, like the rest of the stack
 * configuration, reaches the clients through the `config.json` pushed to the
 * bucket: every write schedules that push.
 */
class ClientThemeAction extends AbstractController
{
    public function __construct(
        private readonly ConfiguratorEntryRepository $repository,
        private readonly ManagerRegistry $doctrine,
        private readonly MessageBusInterface $bus,
        private readonly ClientThemeNormalizer $normalizer,
    ) {
    }

    #[Route(path: '/client-theme', methods: ['GET'])]
    public function get(): Response
    {
        $entry = $this->findEntry();
        $theme = null;
        if (null !== $entry) {
            try {
                $theme = $this->normalizer->normalize(json_decode($entry->getValue(), true, 512, JSON_THROW_ON_ERROR));
            } catch (\JsonException|InvalidClientThemeException) {
                // An entry edited by hand can be invalid: nothing to offer
            }
        }

        return $this->createResponse($theme);
    }

    #[Route(path: '/client-theme', methods: ['PUT'])]
    public function update(Request $request): Response
    {
        $this->denyUnlessAdmin();

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON body');
        }

        try {
            $theme = $this->normalizer->normalize($data);
        } catch (InvalidClientThemeException $e) {
            return new JsonResponse([
                'title' => 'Invalid client theme',
                'detail' => $e->getMessage(),
                'violations' => $e->getViolations(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entry = $this->findEntry();
        if (null === $entry) {
            $entry = new ConfiguratorEntry();
            $entry->setName(DataboxConfigSchema::THEME_KEY);
        }
        $entry->setValue(json_encode($theme, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $em = $this->getEntityManager();
        $em->persist($entry);
        $em->flush();

        $this->bus->dispatch(new DeployConfig());

        return $this->createResponse($theme);
    }

    #[Route(path: '/client-theme', methods: ['DELETE'])]
    public function delete(): Response
    {
        $this->denyUnlessAdmin();

        $entry = $this->findEntry();
        if (null !== $entry) {
            $em = $this->getEntityManager();
            $em->remove($entry);
            $em->flush();

            $this->bus->dispatch(new DeployConfig());
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function findEntry(): ?ConfiguratorEntry
    {
        return $this->repository->findOneBy(['name' => DataboxConfigSchema::THEME_KEY]);
    }

    private function getEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        $em = $this->doctrine->getManagerForClass(ConfiguratorEntry::class);
        if (!$em instanceof \Doctrine\ORM\EntityManagerInterface) {
            throw new \LogicException('No entity manager for configurator entries');
        }

        return $em;
    }

    private function createResponse(?array $theme): Response
    {
        // The constructor turns null into an empty object: set the data
        // afterwards so that "no theme" is serialized as a JSON null.
        $response = new JsonResponse();
        $response->setData($theme);

        return $response;
    }

    private function denyUnlessAdmin(): void
    {
        if (!$this->getUser() instanceof JwtUser) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }

        $this->denyAccessUnlessGranted(JwtUser::ROLE_ADMIN);
    }
}
