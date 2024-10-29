<?php

namespace Alchemy\RenditionFactory\Templating;

interface TemplateResolverInterface
{
    public function resolve(string $template, array $values): string;
}
