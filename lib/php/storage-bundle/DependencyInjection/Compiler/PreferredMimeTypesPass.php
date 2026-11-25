<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\DependencyInjection\Compiler;

use Alchemy\StorageBundle\Util\PreferredMimeTypes;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * Autowires MimeTypesInterface to the MIME types knowing the preferred types of
 * the extensions (rendition-factory included). Done in a pass: the alias of the
 * framework would win over one set by an extension loaded before it.
 */
final class PreferredMimeTypesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setAlias(MimeTypesInterface::class, PreferredMimeTypes::class);
    }
}
