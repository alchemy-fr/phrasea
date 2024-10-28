<?php

namespace Alchemy\RenditionFactory\Config;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

class TemplatedOptions
{
    private ?array $compiledOptions = null;
    private Environment $twig;
    private array $twigContext = [];

    public function __construct(private readonly array $options)
    {
        $this->twig = new Environment(new ArrayLoader(), []);
    }

    public function addContext(string $key, array $context): void
    {
        $this->twigContext[$key] = $context;
        $this->compiledOptions = null;
    }

    public function getTwigContext(): array
    {
        return $this->twigContext;
    }

    public function asArray(): array
    {
        return $this->compiledOptions ?: ($this->compiledOptions = $this->compile($this->options));
    }

    private function compile(array $option): array
    {
        $r = [];
        foreach ($option as $k => $o) {
            if (is_array($o)) {
                $r[$k] = $this->compile($o);
            } else {
                try {
                    $r[$k] = $this->twig->createTemplate($o)->render($this->twigContext);
                } catch (LoaderError|SyntaxError $e) {
                    // not enough context to evaluate ? just ignore this option
                }
            }
        }

        return $r;
    }
}
