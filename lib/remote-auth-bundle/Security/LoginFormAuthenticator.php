<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Security\Client\RemoteClient;
use Alchemy\RemoteAuthBundle\Security\Provider\RemoteAuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractFormLoginAuthenticator
{
    use TargetPathTrait;

    private $entityManager;
    private $router;
    private $csrfTokenManager;
    private $passwordEncoder;

    /**
     * @var RemoteClient
     */
    private $client;
    /**
     * @var string
     */
    private $clientId;
    /**
     * @var string
     */
    private $clientSecret;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var RemoteAuthProvider
     */
    private $userProvider;
    /**
     * @var string
     */
    private $routeName;
    /**
     * @var string
     */
    private $defaultTargetPath;

    public function __construct(
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordEncoderInterface $passwordEncoder,
        RemoteClient $client,
        string $clientId,
        string $clientSecret,
        string $routeName,
        string $defaultTargetPath,
        SessionInterface $session,
        RemoteAuthProvider $userProvider
    ) {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->client = $client;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->session = $session;
        $this->userProvider = $userProvider;
        $this->routeName = $routeName;
        $this->defaultTargetPath = $defaultTargetPath;
    }

    public function supports(Request $request)
    {
        return $this->routeName === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        $credentials = [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];

        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['username']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $csrfToken = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
            throw new InvalidCsrfTokenException();
        }

        try {
            $response = $this->client->post('oauth/v2/token', [
                'json' => [
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
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
        $data = \GuzzleHttp\json_decode($content, true);
        if (!isset($data['access_token'])) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials');
        }

        $accessToken = $data['access_token'];
        $this->session->set('access_token', $data['access_token']);

        $tokenInfo = $this->userProvider->getTokenInfo($accessToken);

        return $this->userProvider->getUserFromToken($tokenInfo);
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->defaultTargetPath);
    }

    protected function getLoginUrl()
    {
        return $this->router->generate($this->routeName);
    }
}
