<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\FamilyBuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\GenerateConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\Transformation;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Exception\ModelException;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlLoader implements FileLoaderInterface
{
    public function load(string $file): BuildConfig
    {
        $data = Yaml::parseFile($file);

        try {
            return $this->parseConfig($data);
        } catch (ModelException $e) {
            throw new \InvalidArgumentException(sprintf('%s in file %s', $e->getMessage(), $file), 0, $e);
        }
    }

    public function parse(string $content): BuildConfig
    {
        $data = Yaml::parse($content);
        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf("Invalid YAML content:\n%s", $content));
        }

        return $this->parseConfig($data);
    }

    private function parseConfig(array $data): BuildConfig
    {
        if (array_key_exists(BuildConfig::GENERATE, $data)) {
            if (!is_array($data[BuildConfig::GENERATE])) {
                throw new ModelException('Invalid generate configuration. Array expected');
            }
            if (count($data) > 1) {
                throw new ModelException(sprintf('"%s" cannot be combined with file type families', BuildConfig::GENERATE));
            }

            return new BuildConfig([], $this->parseGenerateConfig($data[BuildConfig::GENERATE]));
        }

        $families = [];
        foreach ($data as $familyKey => $familyConfig) {
            if (null === $family = FamilyEnum::tryFrom($familyKey)) {
                throw new ModelException(sprintf('Invalid file type family "%s". Expected one of %s', $familyKey, implode(', ', array_map(fn (FamilyEnum $family) => $family->value, FamilyEnum::cases()))));
            }

            if (null !== $familyConfig) {
                if (!is_array($familyConfig)) {
                    throw new ModelException('Invalid family configuration. Array expected');
                }

                $families[$family->value] = $this->parseFamilyConfig($familyConfig);
            }
        }

        return new BuildConfig($families, null);
    }

    private function parseFamilyConfig(array $data): FamilyBuildConfig
    {
        return new FamilyBuildConfig($this->parseTransformations($data), $data['normalization'] ?? []);
    }

    private function parseGenerateConfig(array $data): GenerateConfig
    {
        $input = $data['input'] ?? null;
        if (null !== $input && !is_string($input)) {
            throw new ModelException('Invalid generate input. String expected');
        }

        return new GenerateConfig($this->parseTransformations($data), $data['normalization'] ?? [], $input);
    }

    /**
     * @return Transformation[]
     */
    private function parseTransformations(array $data): array
    {
        if (empty($data['transformations'])) {
            throw new ModelException('Missing transformations');
        }
        if (!is_array($data['transformations'])) {
            throw new ModelException('Invalid transformations. Array expected');
        }

        $transformations = [];
        foreach ($data['transformations'] as $transformation) {
            if (!is_array($transformation) || !isset($transformation['module'])) {
                throw new ModelException('Invalid transformation. A "module" is expected');
            }
            $transformations[] = $this->parseTransformation($transformation);
        }

        return $transformations;
    }

    private function parseTransformation(array $transformation): Transformation
    {
        return new Transformation(
            $transformation['module'],
            $transformation['enabled'] ?? true,
            $transformation['options'] ?? [],
            $transformation['description'] ?? null
        );
    }
}
