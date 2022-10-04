<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\File\FileMetadataAccessorWrapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class FallbackResolver
{
    private Environment $twig;
    private EntityManagerInterface $em;
    private ?array $indexByName = null;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
        $this->em = $em;
        $this->logger = $logger;
    }

    private function getDefinitionIndexByName(string $workspaceId): array
    {
        if (null !== $this->indexByName) {
            return $this->indexByName;
        }

        $definitions = $this->em->getRepository(AttributeDefinition::class)->getWorkspaceDefinitions($workspaceId);
        $this->indexByName = [];

        foreach ($definitions as $definition) {
            $this->indexByName[$definition->getName()] = $definition;
        }

        return $this->indexByName;
    }

    /**
     * @param array<string, array<string, Attribute>> $attributes
     */
    public function resolveAttrFallback(
        Asset $asset,
        string $locale,
        AttributeDefinition $definition,
        array &$attributes,
        array $recursionPath = []   // list of resolved attrDef ids, to detect cross-dependency
    ): ?Attribute {
        $definitionsIndex = $this->getDefinitionIndexByName($asset->getWorkspaceId());
        $fallbacks = $definition->getFallback();

        // todo: remove debug after testing
        $tabs = str_repeat('    ', count($recursionPath));  // recursion depth

        if (!empty($fallbacks[$locale])) {
            // todo: remove debug after testing
            $this->logger->debug(sprintf("$tabs-> resolveAttrFallback for '%s' (locale='%s')", $definition->getName(), $locale));

            $recursionPath[] = $definition->getId();
            $fallbackValue = $this->resolveFallback(
                $fallbacks[$locale],
                [
                    'file' => new FileMetadataAccessorWrapper($asset->getFile(), $this->logger),
                    'asset' => $asset,
                    /*
                     * "attr" allows a fallback (twig) to refrence attr.attributeName, so a -destination- fallback value
                     *    can contain the "value" of another -source- attribute.
                     * If the source attribute also has a fallback and is not yet resolved... recursion
                     */
                    'attr' => new DynamicAttributeBag(
                        $attributes,
                        $definitionsIndex,
                        function (AttributeDefinition $depDef) use ($asset, &$attributes, $locale, $definition, $recursionPath): ?Attribute {
                            if (in_array($depDef->getId(), $recursionPath)) {
                                $this->logger->warning(sprintf("resolveAttrFallback for '%s' (locale='%s'): Cross-dependency detected with '%s'", $definition->getName(), $locale, $depDef->getName()));

                                return null;
                            }

                            return $this->resolveAttrFallback(
                                $asset,
                                $locale,
                                $depDef,
                                $attributes,
                                $recursionPath
                            );
                        },
                        $locale
                    ),
                ]
            );

            if (!isset($attributes[$definition->getId()][$locale])) {
                // no "real" value for this attrDef.: Create a "fallback only" attribute (value=null)
                $attribute = new Attribute();
                $attribute->setCreatedAt(new DateTimeImmutable());
                $attribute->setUpdatedAt(new DateTimeImmutable());
                $attribute->setLocale($locale);
                $attribute->setDefinition($definition);
                $attribute->setAsset($asset);
                $attribute->setOrigin(Attribute::ORIGIN_FALLBACK);

                $attributes[$definition->getId()][$locale] = $attribute;
            } else {
                // existing attribute: will add the fallback value to it
                $attribute = $attributes[$definition->getId()][$locale];
            }

            if ($definition->isMultiple()) {
                // each line becomes a value
                $fallbackValues = array_filter(
                    explode("\n", $fallbackValue),
                    function ($s) {
                        return '' != trim($s);
                    }
                );

                // todo: remove debug after testing
                $this->logger->debug(sprintf("$tabs<- fallback result for '%s' (multi): [%s]", $definition->getName(), join(', ', array_map(function ($v) {return var_export($v, true); }, $fallbackValues))));

                $attribute->setFallbackValues($fallbackValues);
            } else {
                // todo: remove debug after testing
                $this->logger->debug(sprintf("$tabs<- fallback result for '%s' (mono): %s", $definition->getName(), var_export($fallbackValue, true)));

                $attribute->setFallbackValue($fallbackValue);
            }

            return $attribute;
        }

        return null;
    }

    private function resolveFallback(string $fallbackTemplate, array $values): string
    {
        $template = $this->twig->createTemplate($fallbackTemplate);

        return $this->twig->render($template, $values);
    }
}
