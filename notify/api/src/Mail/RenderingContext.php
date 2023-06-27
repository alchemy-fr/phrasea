<?php

declare(strict_types=1);

namespace App\Mail;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RenderingContext
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly UrlGeneratorInterface $router)
    {
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function setLocale(string $locale): void
    {
        \Locale::setDefault($locale);
        $this->translator->setLocale($locale);
        $this->router->getContext()->setParameter('_locale', $locale);
    }
}
