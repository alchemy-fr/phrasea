<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;

class ModuleOptionsResolver
{
    public function __construct(private TemplateResolverInterface $templateResolver)
    {
    }

    public function resolveOptions(array $options, array $context): array
    {
        return $this->compile($options, $context);
    }

    private function compile(array $options, array $context): array
    {
        $r = [];
        foreach ($options as $k => $o) {
            if (is_array($o)) {
                $r[$k] = $this->compile($o, $context);
            } else {
                $r[$k] = is_string($o) ? $this->templateResolver->resolve($o, $context) : $o;
            }
        }

        return $r;
    }
}
