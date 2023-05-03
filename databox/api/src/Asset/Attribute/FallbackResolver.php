<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class FallbackResolver
{
    private readonly Environment $twig;
    private ?array $indexByName = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
    }

    private function getDefinitionIndexByName(string $workspaceId): array
    {
        if (null !== $this->indexByName) {
            return $this->indexByName;
        }

        $definitions = $this->em->getRepository(AttributeDefinition::class)->getWorkspaceDefinitions($workspaceId);
        $this->indexByName = [];

        foreach ($definitions as $definition) {
            $this->indexByName[$definition->getSlug()] = $definition;
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
                $fallbackValue = $this->resolveFallback($fallbacks[$locale], [
                    'file' => $asset->getSource(),
                    'asset' => $asset,
                    'attr' => new DynamicAttributeBag($attributes, $definitionsIndex, function (AttributeDefinition $depDef) use (
                        $asset,
                        &$attributes,
                        $locale
                    ): ?Attribute {
                        return $this->resolveAttrFallback(
                            $asset,
                            $locale,
                            $depDef,
                            $attributes
                        );
                    }, $locale),
                ]);

                $attribute = new Attribute();
                $attribute->setCreatedAt(new DateTimeImmutable());
                $attribute->setUpdatedAt(new DateTimeImmutable());
                $attribute->setLocale($locale);
                $attribute->setDefinition($definition);
                $attribute->setAsset($asset);
                $attribute->setOrigin(Attribute::ORIGIN_FALLBACK);
                $attribute->setValue($fallbackValue);

                if ($definition->isMultiple()) {
                    $attribute->setValues([$fallbackValue]);
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
