<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractIntegration implements IntegrationInterface
{
    private ValidatorInterface $validator;

    /**
     * @required
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function validateConfiguration(array $config): void
    {
    }

    public function getConfiguration(): ?TreeBuilder
    {
        return null;
    }

    public function getConfigurationInfo(array $options): array
    {
        return [];
    }

    public function resolveClientOptions(WorkspaceIntegration $workspaceIntegration, array $options): array
    {
        return [];
    }

    protected function validate($array, $property, $constraints): void
    {
        $violations = $this->validator->validate($array[$property] ?? null, $constraints);

        if ($violations->count() > 0) {
            $a = [];
            foreach ($violations as $violation) {
                /** @var ConstraintViolation $violation */
                $a[] = $violation->getMessage();
            }

            throw new InvalidConfigurationException(sprintf('%s: %s', $property, implode("\n", $a)));
        }
    }
}
