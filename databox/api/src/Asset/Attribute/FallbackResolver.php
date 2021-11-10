<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

class FallbackResolver
{
    private Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
    }

    public function resolveFallback(string $fallbackTemplate, array $values): string
    {
        $template = $this->twig->createTemplate($fallbackTemplate);

        return $this->twig->render($template, $values);
    }
}
