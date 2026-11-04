<?php

declare(strict_types=1);

namespace App\Validator;

use Alchemy\RenditionFactory\Config\BuildConfigValidator;
use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use App\Entity\Core\RenditionDefinition;
use App\Model\AssetTypeEnum;
use App\Service\Asset\StoryContactSheetInputResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidRenditionDefinitionConstraintValidator extends ConstraintValidator
{
    /** @uses BuildConfigValidator */
    public function __construct(private readonly YamlLoader $yamlLoader, private readonly BuildConfigValidator $validator)
    {
    }

    /**
     * @param string                             $value
     * @param ValidRenditionDefinitionConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$value) {
            return;
        }
        try {
            $config = $this->yamlLoader->parse($value);
            $this->validator->validate($config);
        } catch (\Exception $e) {
            $this->context
                ->buildViolation($e->getMessage())
                ->addViolation();

            return;
        }

        if (null !== $generateConfig = $config->getGenerate()) {
            $this->validateGenerate($generateConfig->getInput());
        }
    }

    /**
     * A "generate" build assembles the renditions of the story items.
     */
    private function validateGenerate(?string $input): void
    {
        $object = $this->context->getObject();
        if (!$object instanceof RenditionDefinition) {
            $this->context
                ->buildViolation(sprintf('"%s" is only supported by rendition definitions.', BuildConfig::GENERATE))
                ->addViolation();

            return;
        }

        if (null !== $object->getParent()) {
            $this->context
                ->buildViolation(sprintf('A "%s" rendition is built from the story items, it cannot have a parent.', BuildConfig::GENERATE))
                ->addViolation();
        }

        if (AssetTypeEnum::Story !== $object->getTarget()) {
            $this->context
                ->buildViolation(sprintf('A "%s" rendition is built for stories only: its target must be "Story".', BuildConfig::GENERATE))
                ->addViolation();
        }

        if (null !== $input && !in_array($input, StoryContactSheetInputResolver::INPUTS, true)) {
            $this->context
                ->buildViolation(sprintf('Invalid "%s" input "%s", expected one of: %s.', BuildConfig::GENERATE, $input, implode(', ', StoryContactSheetInputResolver::INPUTS)))
                ->addViolation();
        }
    }
}
