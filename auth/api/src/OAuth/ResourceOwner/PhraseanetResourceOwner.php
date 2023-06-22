<?php

declare(strict_types=1);

namespace App\OAuth\ResourceOwner;

use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\GenericOAuth2ResourceOwner;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhraseanetResourceOwner extends GenericOAuth2ResourceOwner implements ResourceOwnerInterface
{
    /**
     * {@inheritdoc}
     */
    protected array $paths = [
        'identifier' => 'response.user.id',
        'nickname' => 'response.user.email',
        'realname' => 'response.user.email',
        'email' => 'response.user.email',
    ];

    public static function getTypeName(): string
    {
        return 'phraseanet';
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setRequired(['base_url']);

        $resolver->setDefaults([
            'authorization_url' => function (Options $options) {
                return $options['base_url'].'/oauthv2/authorize';
            },
            'access_token_url' => function (Options $options) {
                return $options['base_url'].'/oauthv2/token';
            },
            'infos_url' => function (Options $options) {
                return $options['base_url'].'/v1/me';
            },
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInformation(
        array $accessToken,
        array $extraParameters = []
    ) {
        $content = $this->httpRequest(
            $this->normalizeUrl($this->options['infos_url'],
                $extraParameters),
            'null',
            [
                'Authorization' => 'OAuth'.' '.$accessToken['access_token'],
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
            ], 'GET');

        $response = $this->getUserResponse();

        $response->setData($content instanceof ResponseInterface ? (string) $content->getBody() : $content);
        $response->setResourceOwner($this);
        $response->setOAuthToken(new OAuthToken($accessToken));

        return $response;
    }
}
