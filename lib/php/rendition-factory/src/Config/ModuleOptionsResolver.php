<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

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
                try {
                    $r[$k] = $this->templateResolver->resolve($o, $context);
                } catch (SyntaxError|LoaderError $e) {
                    throw $e;
                } catch (\Exception $e) {
                    // not enough context to evaluate ? just ignore this option for now
                }
            }
        }

        return $r;
    }
}
