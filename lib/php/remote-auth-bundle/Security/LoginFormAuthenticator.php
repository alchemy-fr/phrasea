<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Security\Provider\RemoteAuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Utils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly AuthServiceClient $client,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $routeName,
        private readonly string $defaultTargetPath,
        private readonly RequestStack $requestStack,
        private readonly RemoteAuthProvider $userProvider,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->routeName === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('username');
        $password = $request->request->get('password');

        return new Passport(
            new UserBadge($username, function($userIdentifier) use ($username, $password) {
                try {
                    $response = $this->client->post('oauth/v2/token', [
                        'json' => [
                            'username' => $userIdentifier,
                            'password' => $password,
                            'grant_type' => 'password',
                            'client_id' => $this->clientId,
                            'client_secret' => $this->clientSecret,
                        ],
                    ]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (401 === $response->getStatusCode()) {
                        $json = \GuzzleHttp\json_decode($response->getBody()->getContents());
                        throw new CustomUserMessageAuthenticationException($json['error_description']);
                    }
                }

                $content = $response->getBody()->getContents();
                $data = Utils::jsonDecode($content, true);
                if (!isset($data['access_token'])) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials');
                }

                $accessToken = $data['access_token'];
                $this->requestStack->getSession()->set('access_token', $data['access_token']);

                $tokenInfo = $this->userProvider->getTokenInfo($accessToken);

                return $this->userProvider->getUserFromToken($tokenInfo);
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge(
                    'authenticate',
                    $request->request->get('_csrf_token')
                ),
//                (new RememberMeBadge())->enable(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        if ($redirectUri = $request->query->get('r')) {
            return new RedirectResponse($redirectUri);
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->defaultTargetPath);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate($this->routeName);
    }

    /**
     * Override to control what happens when the user hits a secure page
     * but isn't logged in yet.
     *
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $url = $this->getLoginUrl($request).'?r='.urlencode($request->getUri());

        return new RedirectResponse($url);
    }
}
