<?php

namespace App\Asset\Attribute;

use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final readonly class TemplateResolver implements TemplateResolverInterface
{
    private Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
    }

    public function resolve(string $template, array $values): string
    {
        if (str_contains($template, '{')) {
            return $this->twig->createTemplate($template)->render($values);
        }

        return $template;
    }
}
