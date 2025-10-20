<?php

declare(strict_types=1);

namespace App\Validator;

use App\Border\FileAnalyzer;
use App\Entity\Core\Workspace;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Yaml\Yaml;

class ValidAnalyzersOptionsConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly FileAnalyzer $fileAnalyzer)
    {
    }

    /**
     * @param Workspace                         $value
     * @param ValidIntegrationOptionsConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (empty($value->getFileAnalyzers())) {
            return;
        }

        $analyzers = Yaml::parse($value->getFileAnalyzers());

        try {
            $this->fileAnalyzer->validateAnalyzersConfiguration($analyzers);
        } catch (InvalidConfigurationException|\InvalidArgumentException $e) {
            $this->context
                ->buildViolation($e->getMessage())
                ->atPath('fileAnalyzers')
                ->addViolation();
        }
    }
}
