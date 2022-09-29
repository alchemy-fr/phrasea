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
        array &$attributes
    ): ?Attribute {
        $definitionsIndex = $this->getDefinitionIndexByName($asset->getWorkspaceId());
        $fallbacks = $definition->getFallback();

        if (!empty($fallbacks[$locale])) {
            if (!isset($attributes[$definition->getId()][$locale])) {
                // todo: remove debug after testing
                $this->logger->debug(sprintf("resolveAttrFallback for '%s' (locale='%s')", $definition->getName(), $locale));
                $fallbackValue = $this->resolveFallback(
                    $fallbacks[$locale],
                    [
                        'file' => new FileMetadataAccessorWrapper($asset->getFile()),
                        'asset' => $asset,
                        'attr' => new DynamicAttributeBag(
                            $attributes,
                            $definitionsIndex,
                            function (AttributeDefinition $depDef) use ($asset, &$attributes, $locale): ?Attribute {
                                // todo: remove debug after testing
                                $this->logger->debug(sprintf('resolveAttrFallback recurse)'));

                                return $this->resolveAttrFallback(
                                    $asset,
                                    $locale,
                                    $depDef,
                                    $attributes
                                );
                            },
                            $locale
                        ),
                    ]
                );

                $attribute = new Attribute();
                $attribute->setCreatedAt(new DateTimeImmutable());
                $attribute->setUpdatedAt(new DateTimeImmutable());
                $attribute->setLocale($locale);
                $attribute->setDefinition($definition);
                $attribute->setAsset($asset);
                $attribute->setOrigin(Attribute::ORIGIN_FALLBACK);

                if ($definition->isMultiple()) {
                    // each line becomes a value
                    $values = array_filter(
                        explode("\n", $fallbackValue),
                        function ($s) { return '' != trim($s); }
                    );

                    // todo: remove debug after testing
                    $this->logger->debug(sprintf("fallback result for '%s' (multi) : %s", $definition->getName(), var_export($values, true)));
                    $attribute->setValues($values);
                } else {
                    // todo: remove debug after testing
                    $this->logger->debug(sprintf("fallback result for '%s' (mono) <- %s", $definition->getName(), var_export($fallbackValue, true)));
                    $attribute->setValue($fallbackValue);
                }

                $attributes[$definition->getId()][$locale] = $attribute;

                return $attribute;
            }
        }

        return null;
    }

    private function resolveFallback(string $fallbackTemplate, array $values): string
    {
        $template = $this->twig->createTemplate($fallbackTemplate);

        return $this->twig->render($template, $values);
    }
}
