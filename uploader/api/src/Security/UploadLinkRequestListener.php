<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\UploadConfigAction;
use App\Entity\UploadLink;
use App\Repository\UploadLinkRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * An upload link only opens the upload flow of its target: any other endpoint is denied, whatever its own
 * security says. And only while its creator could still create it (see UploadLinkCreatorChecker).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
final readonly class UploadLinkRequestListener
{
    /**
     * After the firewall (8), which authenticates the link.
     */
    private const int PRIORITY = 4;

    final public const string ERROR_REVOKED = 'upload_link_revoked';

    /**
     * @var array<string, list<string>> route name => allowed methods
     */
    private const array ALLOWED_ROUTES = [
        UploadLink::ROUTE_CURRENT => ['GET'],
        UploadConfigAction::ROUTE_NAME => ['GET'],
        '_api_/targets/{id}{._format}_get' => ['GET'],
        'get_target_form' => ['GET'],
        'form_field_options' => ['GET'],
        '_api_/form/validate_post' => ['POST'],
        '_api_/uploads{._format}_post' => ['POST'],
        '_api_/uploads/{id}{._format}_get' => ['GET'],
        '_api_/uploads/{id}{._format}_delete' => ['DELETE'],
        '_api_/uploads/{id}/parts_post' => ['POST'],
        '_api_/uploads/{id}/part_post' => ['POST'],
        '_api_/assets{._format}_post' => ['POST'],
        '_api_/assets/{id}{._format}_delete' => ['DELETE'],
        '_api_/commit_post' => ['POST'],
    ];

    public function __construct(
        private Security $security,
        private UploadLinkRepository $repository,
        private UploadLinkCreatorChecker $creatorChecker,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof UploadLinkUser) {
            return;
        }

        $request = $event->getRequest();
        if ('OPTIONS' === $request->getMethod()) {
            return;
        }

        $route = (string) $request->attributes->get('_route');
        if (!in_array($request->getMethod(), self::ALLOWED_ROUTES[$route] ?? [], true)) {
            throw new AccessDeniedHttpException('Not available through an upload link');
        }

        $link = $this->repository->find($user->getLinkId());
        if (!$link instanceof UploadLink || !$this->creatorChecker->isStillAllowed($link)) {
            // Same shape as the refusals of UploadLinkAuthenticator
            $event->setResponse(new JsonResponse([
                'code' => self::ERROR_REVOKED,
                'message' => 'This upload link is no longer valid',
            ], Response::HTTP_FORBIDDEN));
        }
    }
}
