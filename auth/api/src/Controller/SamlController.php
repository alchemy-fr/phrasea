<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use OAuth2\OAuth2;
use OAuth2\OAuth2ServerException;
use OneLogin\Saml2\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * @Route("/saml", name="saml_")
 */
class SamlController extends AbstractIdentityProviderController
{
    /**
     * @Route(path="/{provider}/authorize", name="authorize")
     */
    public function authorize(string $provider, Auth $samlAuth, Request $request)
    {
        $clientId = $request->get('client_id');
        if (!$clientId) {
            throw new BadRequestHttpException('Missing client_id parameter');
        }
        $lastRedirectUri = $request->get('redirect_uri');
        if (!$lastRedirectUri) {
            throw new BadRequestHttpException('Missing redirect_uri parameter');
        }

        $session = $request->getSession();
        $authErrorKey = Security::AUTHENTICATION_ERROR;

        if ($request->attributes->has($authErrorKey)) {
            $error = $request->attributes->get($authErrorKey);
        } elseif (null !== $session && $session->has($authErrorKey)) {
            $error = $session->get($authErrorKey);
            $session->remove($authErrorKey);
        } else {
            $error = null;
        }

        if ($error) {
            throw new RuntimeException($error->getMessage());
        }

        $session->set('_security.saml.target_path', $this->generateUrl('saml_check', $this->getRedirectParams(
            $provider,
            $lastRedirectUri,
            $clientId
        )));

        $samlAuth->login();
    }

    /**
     * @Route(path="/{provider}/check", name="check")
     */
    public function check(
        string $provider,
        OAuth2 $oAuth2Server,
        Request $request
    ) {
        $finalRedirectUri = $request->get('r');
        $clientId = $request->get('cid');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \Exception('User is not authenticated from SAML');
        }

        $scope = $request->get('scope');
        $subRequest = new Request();
        $subRequest->query->set('client_id', $clientId);
        $subRequest->query->set('redirect_uri', $finalRedirectUri);
        $subRequest->query->set('response_type', 'code');

        try {
            return $oAuth2Server->finishClientAuthorization(true, $user, $subRequest, $scope);
        } catch (OAuth2ServerException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }
}
