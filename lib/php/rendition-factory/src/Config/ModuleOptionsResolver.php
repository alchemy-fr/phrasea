<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

class ModuleOptionsResolver
{
    private array $options = [];
    private array $context = [];
    private ?array $resolvedOptions = null;

    public function __construct(private TemplateResolverInterface $templateResolver)
    {
    }

    public function load(array $options): void
    {
        $this->options = $options;
        $this->resolvedOptions = null;
    }

    public function addContext(string $key, array $context): void
    {
        $this->context[$key] = $context;
        $this->resolvedOptions = null;
    }

    public function getResolvedOptions(): array
    {
        return $this->resolvedOptions ?: ($this->resolvedOptions = $this->compile($this->options));
    }

    /**
     * @throws SyntaxError
     * @throws LoaderError
     */
    private function compile(array $option): array
    {
        $r = [];
        foreach ($option as $k => $o) {
            if (is_array($o)) {
                $r[$k] = $this->compile($o);
            } else {
                try {
                    $r[$k] = $this->templateResolver->resolve($o, $this->context);
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
