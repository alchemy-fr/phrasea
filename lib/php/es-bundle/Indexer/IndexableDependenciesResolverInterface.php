<?php

namespace Alchemy\ESBundle\Indexer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface IndexableDependenciesResolverInterface
{
    final public const TAG = 'alchemy_es.dependency_resolver';

    public function setAddToParentsClosure(\Closure $closure): void;

    public function setAddDependencyClosure(\Closure $closure): void;

    public function updateDependencies(ESIndexableDependencyInterface $object): void;
}
