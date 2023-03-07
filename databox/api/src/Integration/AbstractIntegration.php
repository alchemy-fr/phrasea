<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
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

    public function buildConfiguration(NodeBuilder $builder): void
    {
    }

    public function getConfigurationInfo(array $config): array
    {
        return [];
    }

    protected function createBudgetLimitConfigNode(
        bool $defaultEnabled = false,
        int $limit = 1000,
        string $interval = '1 year'
    ): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('budgetLimit');

        $root = $treeBuilder->getRootNode();

        if ($defaultEnabled) {
            $root->canBeDisabled();
        } else {
            $root->canBeEnabled();
        }

        $root
            ->children()
                ->enumNode('policy')
                    ->defaultValue('sliding_window')
                    ->values(ApiBudgetLimiter::POLICIES)
                ->end()
                ->integerNode('limit')
                    ->defaultValue($limit)
                ->end()
                ->scalarNode('interval')
                    ->defaultValue($interval)
                    ->example([
                        '12 hours',
                        '3 months',
                        '1 day',
                        '1 year',
                    ])
                    ->info('Analyze all incoming assets automatically')
                ->end()
            ->end()
        ;

        return $treeBuilder->getRootNode();
    }

    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, array $config): array
    {
        return [];
    }

    protected function validate($array, $property, $constraints): void
    {
        $violations = $this->validator->validate($array[$property] ?? null, $constraints);

        if ($violations->count() > 0) {
            $a = [];
            foreach ($violations as $violation) {
                /* @var ConstraintViolation $violation */
                $a[] = $violation->getMessage();
            }

            throw new InvalidConfigurationException(sprintf('%s: %s', $property, implode("\n", $a)));
        }
    }
}
