<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\File\FileMetadataAccessorWrapper;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class InitialAttributeValuesResolver
{
    private Environment $twig;
    private EntityManagerInterface $em;
    private AttributeAssigner $attributeAssigner;

    public function __construct(
        EntityManagerInterface $em,
        AttributeAssigner $attributeAssigner
    ) {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
        $this->em = $em;
        $this->attributeAssigner = $attributeAssigner;
    }

    /**
     * @return array<string, array<string, Attribute>>
     */
    public function resolveInitialAttributes(Asset $asset): array
    {
        $attributes = [];

        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $this->em->getRepository(AttributeDefinition::class);

        $definitions = $repo->getWorkspaceInitializeDefinitions($asset->getWorkspaceId());
        $fileMetadataAccessorWrapper = new FileMetadataAccessorWrapper($asset->getSource());

        foreach ($definitions as $definition) {
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

                    $position = 0;
                    $now = new DateTimeImmutable();
                    foreach ($initialValues as $initialValue) {
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

                        $this->attributeAssigner->assignAttributeFromInput($attribute, $input);
                        if (null === $attribute->getValue()) {
                            // this can happen for e.g. if a date is invalid and cannot be normalized
                            continue;
                        }

                        $attributes[] = $attribute;
                    }
                }
            }
        }

        return $attributes;
    }

    private function resolveInitial(
        Asset $asset,
        FileMetadataAccessorWrapper $fileMetadataAccessorWrapper,
        string $initializeFormula,
        AttributeDefinition $definition
    ): array {

        $initialValues = [];
        $initializeFormula = json_decode($initializeFormula, true, 512, JSON_THROW_ON_ERROR);

        switch ($initializeFormula['type']) {
            case 'metadata':
                // the value is a simple metadata tagname, fetch data directly
                $m = $fileMetadataAccessorWrapper->getMetadata($initializeFormula['value']);
                $initialValues = $definition->isMultiple() ? $m['values'] : [$m['value']];
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
                throw new InvalidArgumentException(sprintf('"%s" is not a valid initialization type for attribute "%s"', $initializeFormula['type'], $definition->getName()));
        }

        // remove empty values
        return array_filter(
            $initialValues,
            function (string $s): bool {
                return !empty(trim($s));
            });
    }
}