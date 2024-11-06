<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;

class ModuleOptionsResolver
{
    public function __construct(private TemplateResolverInterface $templateResolver)
    {
    }

    public function resolveOption(mixed $option, array $context): mixed
    {
        return is_string($option) ? $this->templateResolver->resolve($option, $context) : $option;
    }
}
