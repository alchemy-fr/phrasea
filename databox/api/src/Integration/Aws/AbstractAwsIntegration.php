<?php

declare(strict_types=1);

namespace App\Integration\Aws;

use App\Integration\AbstractIntegration;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

abstract class AbstractAwsIntegration extends AbstractIntegration
{
    abstract protected function getSupportedRegions(): array;

    protected function addRegionConfigNode(NodeBuilder $builder): void
    {
        $supportedRegions = $this->getSupportedRegions();

        $builder
            ->scalarNode('region')
                ->cannotBeEmpty()
                ->defaultValue('eu-central-1')
                ->example('us-east-2')
                ->validate()
                ->ifNotInArray($supportedRegions)
                ->thenInvalid(sprintf('Invalid region "%%s". Supported ones are: "%s"', implode('", "', $supportedRegions)))
                ->end()
                ->info(sprintf('Supported regions are: "%s"', implode('", "', $supportedRegions)))
            ->end();
    }

    protected function addCredentialConfigNode(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('accessKeyId')
                ->defaultValue('${AWS_ACCESS_KEY_ID}')
                ->cannotBeEmpty()
                ->info('The AWS IAM Access Key ID')
            ->end()
            ->scalarNode('accessKeySecret')
                ->defaultValue('${AWS_ACCESS_KEY_SECRET}')
                ->cannotBeEmpty()
                ->info('The AWS IAM Access Key Secret')
            ->end();
    }

    protected function getTagByKey(array $tags, string $key): ?string
    {
        foreach ($tags as $tag) {
            if ($tag['Key'] === $key) {
                return $tag['Value'];
            }
        }

        return null;
    }
}
