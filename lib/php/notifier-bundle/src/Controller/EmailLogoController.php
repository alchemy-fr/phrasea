<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller;

use Alchemy\NotifierBundle\Email\EmailLogoResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the logo of the email header, publicly: email clients load it
 * without any credential.
 */
final readonly class EmailLogoController
{
    public const string ROUTE = 'alchemy_notifier_email_logo';

    private const int MAX_AGE = 3600;
    private const int VERSIONED_MAX_AGE = 31536000;

    public function __construct(
        private EmailLogoResolver $logoResolver,
    ) {
    }

    #[Route('/notifications/email-logo', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $logo = $this->logoResolver->resolve();

        if (null !== $logo->remoteUrl) {
            return new RedirectResponse($logo->remoteUrl);
        }

        $response = new Response($logo->content, Response::HTTP_OK, [
            'Content-Type' => $logo->mimeType,
            'X-Content-Type-Options' => 'nosniff',
            // An SVG opened directly must not run scripts on the API origin
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
        $response->setPublic();

        // The layout links to the current version: such a URL never changes content
        if ($request->query->get('v') === $logo->getVersion()) {
            $response->setMaxAge(self::VERSIONED_MAX_AGE);
            $response->setImmutable();
        } else {
            $response->setMaxAge(self::MAX_AGE);
        }

        return $response;
    }
}
