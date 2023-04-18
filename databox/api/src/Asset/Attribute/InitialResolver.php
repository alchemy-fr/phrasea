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

class InitialResolver
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

        // only get attrDefs with initializers setting
        $definitions = $repo->getWorkspaceInitializeDefinitions($asset->getWorkspaceId());
        foreach ($definitions as $definition) {
            $initializers = $definition->getInitializers();

            if (null !== $initializers) {
                foreach ($initializers as $locale => $initializeFormula) {
                    $initialValue = $this->resolveInitial(
                        $initializeFormula,
                        [
                            'file' => new FileMetadataAccessorWrapper($asset->getSource()),
                            'asset' => $asset,
                        ],
                        $definition
                    );

                    $initialValues = [];
                    if ($definition->isMultiple()) {
                        // each line becomes a value
                        $initialValues = array_filter(
                            explode("\n", $initialValue),
                            function (string $s): bool {
                                return !empty(trim($s));
                            }
                        );
                    } else {
                        if (!empty($initialValue = trim($initialValue))) {
                            $initialValues = [$initialValue];
                        }
                    }

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
        string $initializeFormula,
        array $twigContext,
        AttributeDefinition $definition
    ): string {
        $templateFormula = false;

        try {
            $initializeFormula = json_decode($initializeFormula, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            // not json ? assume this is plain twig template
            $templateFormula = $initializeFormula;
        }

        if (false === $templateFormula) {
            // assume this is json formula
            if ('metadata' == $initializeFormula['type']) {
                // the "source" is a simple metadata tagname, convert it to twig
                $templateFormula = sprintf("{%% for v in file.metadata('%s').values %%}{{v}}\n{%% endfor %%}", $initializeFormula['value']);
            } else {
                if ('template' == $initializeFormula['type']) {
                    $templateFormula = $initializeFormula['value'];
                } else {
                    throw new InvalidArgumentException(sprintf('"%s" is not a valid template type for attribute "%s"', $initializeFormula['type'], $definition->getName()));
                }
            }
        }

        $template = $this->twig->createTemplate($templateFormula);

        return $this->twig->render($template, $twigContext);
    }
}
