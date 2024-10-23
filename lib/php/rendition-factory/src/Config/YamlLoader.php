<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\FamilyBuildConfig;
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

    private function removeDisabled(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($value['enabled'] ?? true) {
                    $out[$key] = $this->removeDisabled($value);
                }
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function parseConfig(array $data): BuildConfig
    {
        $data = $this->removeDisabled($data);

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

        return new BuildConfig($families);
    }

    private function parseFamilyConfig(array $data): FamilyBuildConfig
    {
        if (empty($data['transformations'])) {
            throw new ModelException('Missing transformations');
        }

        $transformations = [];
        foreach ($data['transformations'] as $transformation) {
            $transformations[] = $this->parseTransformation($transformation);
        }

        return new FamilyBuildConfig($transformations, $data['normalization'] ?? []);
    }

    private function parseTransformation(array $transformation): Transformation
    {
        return new Transformation(
            $transformation['module'],
            $transformation['options'] ?? [],
            $transformation['description'] ?? null
        );
    }
}
