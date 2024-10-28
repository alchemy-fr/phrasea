<?php

namespace Alchemy\RenditionFactory\Templating;

interface TemplateResolverInterface
{
    public function resolve($template, array $values): string;
}
