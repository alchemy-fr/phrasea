<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;
use App\Twig\Sandbox\TemplateSecurityPolicy;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;

final readonly class TemplateResolver implements TemplateResolverInterface
{
    private Environment $twig;

    public function __construct()
    {
        $this->twig = self::createEnvironment();
    }

    /**
     * Templates are written by workspace editors: they are always rendered in the sandbox.
     */
    public static function createEnvironment(): Environment
    {
        $twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
        $twig->addExtension(new SandboxExtension(new TemplateSecurityPolicy(), true));

        return $twig;
    }

    public function resolve(string $template, array $values): string
    {
        if (str_contains($template, '{')) {
            return $this->twig->createTemplate($template)->render($values);
        }

        return $template;
    }
}
