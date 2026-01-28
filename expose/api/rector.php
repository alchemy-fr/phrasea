<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    // register a single rule
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        Rector\Doctrine\Set\DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        Rector\Symfony\Set\SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        Rector\Symfony\Set\SensiolabsSetList::FRAMEWORK_EXTRA_61,
    ]);
    $rectorConfig->ruleWithConfiguration(AnnotationToAttributeRector::class, [
        new AnnotationToAttribute(ApiPlatform\Core\Annotation\ApiFilter::class),
        new AnnotationToAttribute(Gedmo\Mapping\Annotation\Slug::class),
        new AnnotationToAttribute(ApiPlatform\Core\Annotation\ApiProperty::class),
        new AnnotationToAttribute(ApiPlatform\Core\Annotation\ApiResource::class),
        new AnnotationToAttribute(Gedmo\Mapping\Annotation\SoftDeleteable::class),
        new AnnotationToAttribute(Gedmo\Mapping\Annotation\Timestampable::class),
    ]);

    $rectorConfig->skip([
        Rector\Php71\Rector\FuncCall\CountOnNullRector::class,
    ]);
};
