<?php

declare(strict_types=1);

namespace App\OAuth\ResourceOwner;

use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\GenericOAuth2ResourceOwner;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OpenIdConnectResourceOwner extends GenericOAuth2ResourceOwner implements ResourceOwnerInterface
{
    public static function getTypeName(): string
    {
        return 'openid-connect';
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'protocol' => 'openid-connect',
            'scope' => 'openid email',
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'authorization_url' => '{base_url}/auth',
            'access_token_url' => '{base_url}/token',
            'infos_url' => '{base_url}/userinfo',
        ]);

        $resolver->setRequired([
            'base_url',
        ]);

        $normalizer = fn (Options $options, $value): string => str_replace(
            '{base_url}',
            $options['base_url'],
            (string) $value
        );

        $resolver->setNormalizer('authorization_url', $normalizer);
        $resolver->setNormalizer('access_token_url', $normalizer);
        $resolver->setNormalizer('infos_url', $normalizer);
    }
}
