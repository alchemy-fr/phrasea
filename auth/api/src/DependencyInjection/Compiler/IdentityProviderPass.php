<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\OAuth\GroupParser;
use App\User\GroupMapper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class IdentityProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $groupMapperDef = $container->getDefinition(GroupMapper::class);
        $groupParserDef = $container->getDefinition(GroupParser::class);
        $providers = $container->getParameter('app.identity_providers');

        $maps = [];
        $groupNormalizers = [];
        foreach ($providers as $provider) {
            $maps[$provider['name']] = $provider['group_map'] ?? [];
            if (isset($provider['group_jq_normalizer'])) {
                $groupNormalizers[$provider['name']] = $provider['group_jq_normalizer'];
            }
        }

        $groupMapperDef->setArgument('$groupMaps', $maps);
        $groupParserDef->setArgument('$normalizers', $groupNormalizers);
    }
}
