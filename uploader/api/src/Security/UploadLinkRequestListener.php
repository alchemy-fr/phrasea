<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\UploadLink;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * An upload link only opens the upload flow of its target: any other endpoint is denied, whatever its own
 * security says.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
final readonly class UploadLinkRequestListener
{
    /**
     * After the firewall (8), which authenticates the link.
     */
    private const int PRIORITY = 4;

    /**
     * @var array<string, list<string>> route name => allowed methods
     */
    private const array ALLOWED_ROUTES = [
        UploadLink::ROUTE_CURRENT => ['GET'],
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
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->security->getUser() instanceof UploadLinkUser) {
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
    }
}
