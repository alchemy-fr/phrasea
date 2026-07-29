<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Attribute\AttributeInterface;
use App\Attribute\InvalidAttributeValueException;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\File\FileMetadataAccessorWrapper;
use App\Repository\Core\AttributeDefinitionRepository;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

readonly class InitialAttributeValuesResolver
{
    private Environment $twig;

    public function __construct(
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeAssigner $attributeAssigner,
    ) {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
    }

    /**
     * @return Attribute
     */
    public function resolveInitialAttributes(Asset $asset): array
    {
        $attributes = [];

        $definitions = $this->attributeDefinitionRepository->getWorkspaceInitializeDefinitions($asset->getWorkspaceId());
        $fileMetadataAccessorWrapper = new FileMetadataAccessorWrapper($asset->getSource());

        foreach ($definitions as $definition) {
            $readFromMetadata = $definition->getReadFromMetadata();
            if (null !== $readFromMetadata) {
                $initialValues = $this->resolveFromMetadata($fileMetadataAccessorWrapper, $readFromMetadata, $definition);
                $this->createAttributes($asset, $definition, AttributeInterface::NO_LOCALE, $initialValues, $attributes);
            }

            $initializers = $definition->getInitialValues();

            if (null !== $initializers) {
                foreach ($initializers as $locale => $initializeFormula) {
                    // TODO : handle locales ? now multiple locales initializers will fetch the same data since metadata is not localized
                    $initialValues = $this->resolveInitial(
                        $asset,
                        $fileMetadataAccessorWrapper,
                        $initializeFormula,
                        $definition
                    );

                    $this->createAttributes($asset, $definition, $locale, $initialValues, $attributes);
                }
            }
        }

        return $attributes;
    }

    /**
     * @param string[]    $initialValues
     * @param Attribute[] $attributes
     */
    private function createAttributes(
        Asset $asset,
        AttributeDefinition $definition,
        string $locale,
        array $initialValues,
        array &$attributes,
    ): void {
        $position = 0;
        $now = new \DateTimeImmutable();
        foreach ($initialValues as $initialValue) {
            try {
                $normalizedValue = $this->attributeAssigner->normalizeValue($definition, $initialValue);
            } catch (InvalidAttributeValueException) {
                // this can happen for e.g. if a date is invalid and cannot be normalized
                continue;
            }

            if (null === $normalizedValue) {
                continue;
            }

            $input = new AttributeInput();
            $input->value = $initialValue;
            $input->locale = $locale;
            $input->asset = $asset;
            $input->origin = Attribute::ORIGIN_LABELS[Attribute::ORIGIN_INITIAL];
            $input->definitionId = $definition->getId();
            $input->position = $position++;
            $input->status = Attribute::STATUS_VALID;

            $attribute = new Attribute();
            $attribute->setDefinition($definition);
            $attribute->setCreatedAt($now);
            $attribute->setUpdatedAt($now);
            $attribute->setAsset($asset);

            $this->attributeAssigner->assignAttributeFromInput($attribute, $input, $normalizedValue);
            $this->attributeAssigner->resetAssetAttributesCache($asset);

            $attributes[] = $attribute;
        }
    }

    /**
     * Return the values of the first metadata tag found in the file, among the given list.
     *
     * @param string[] $tags
     *
     * @return string[]
     */
    private function resolveFromMetadata(
        FileMetadataAccessorWrapper $fileMetadataAccessorWrapper,
        array $tags,
        AttributeDefinition $definition,
    ): array {
        foreach ($tags as $tag) {
            $m = $fileMetadataAccessorWrapper->getMetadata($tag);
            if (null === $m) {
                continue;
            }

            $initialValues = $definition->isMultiple() ? $m['values'] : [$m['value']];

            return $this->filterEmptyValues($initialValues);
        }

        return [];
    }

    private function resolveInitial(
        Asset $asset,
        FileMetadataAccessorWrapper $fileMetadataAccessorWrapper,
        string $initializeFormula,
        AttributeDefinition $definition,
    ): array {
        $initializeFormula = json_decode($initializeFormula, true, 512, JSON_THROW_ON_ERROR);

        switch ($initializeFormula['type']) {
            case 'metadata':
                // the value is a simple metadata tag name, fetch data directly
                $m = $fileMetadataAccessorWrapper->getMetadata($initializeFormula['value']);
                $initialValues = $m ? ($definition->isMultiple() ? $m['values'] : [$m['value']]) : [];
                break;

            case 'template':
                // the value is twig code
                $template = $this->twig->createTemplate($initializeFormula['value']);
                $context = [
                    'file' => $fileMetadataAccessorWrapper,
                    'asset' => $asset,
                ];
                $twigOutput = $this->twig->render($template, $context);

                // to return multiple values via twig : one per line
                $initialValues = $definition->isMultiple() ? explode("\n", $twigOutput) : [$twigOutput];
                break;

            default:
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid initialization type for attribute "%s"', $initializeFormula['type'], $definition->getName()));
        }

        return $this->filterEmptyValues($initialValues);
    }

    /**
     * @param array<?string> $values
     *
     * @return string[]
     */
    private function filterEmptyValues(array $values): array
    {
        return array_filter(
            $values,
            function (?string $s): bool {
                if (null === $s) {
                    return false;
                }

                return !empty(trim($s));
            });
    }
}
