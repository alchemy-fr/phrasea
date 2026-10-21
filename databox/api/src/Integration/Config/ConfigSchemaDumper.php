<?php

declare(strict_types=1);

namespace App\Integration\Config;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;

/**
 * Describes a configuration tree for the client form.
 *
 * The schema is inspired by JSON Schema without following it: every node has a "type"
 * (object, list, string, integer, float, boolean, enum, any) and the UI hints declared
 * on the tree (see ConfigWidget).
 */
final readonly class ConfigSchemaDumper
{
    public function dump(NodeInterface $root): array
    {
        if ($root instanceof ArrayNode && !$root instanceof PrototypedArrayNode) {
            return [
                'type' => 'object',
                'properties' => $this->dumpChildren($root),
            ];
        }

        return $this->dumpNode($root);
    }

    private function dumpNode(NodeInterface $node): array
    {
        $schema = $this->dumpType($node);

        $label = self::humanize($node->getName());
        if ('' !== $label) {
            $schema['label'] = $label;
        }

        if ($node instanceof BaseNode) {
            if (null !== $info = $node->getInfo()) {
                $schema['info'] = $info;
            }
            if (null !== $example = $node->getExample()) {
                $schema['example'] = $example;
            }
        }

        if ($node->isRequired()) {
            $schema['required'] = true;
        }

        if ($node->hasDefaultValue()) {
            $schema['default'] = self::normalizeValue($node->getDefaultValue());
        }

        if ($node instanceof BaseNode) {
            if (null !== $widget = $node->getAttribute(ConfigWidget::ATTR_WIDGET)) {
                $schema['widget'] = $widget;
            }
            if (null !== $options = $node->getAttribute(ConfigWidget::ATTR_WIDGET_OPTIONS)) {
                $schema['widgetOptions'] = $options;
            }
            if ($node->getAttribute(ConfigWidget::ATTR_SECRET)) {
                $schema['secret'] = true;
            }
        }

        return $schema;
    }

    private function dumpType(NodeInterface $node): array
    {
        if ($node instanceof PrototypedArrayNode) {
            $schema = [
                'type' => 'list',
                'items' => $this->dumpNode($node->getPrototype()),
            ];
            $minItems = self::getMinItems($node);
            if ($minItems > 0) {
                $schema['minItems'] = $minItems;
            }

            return $schema;
        }

        if ($node instanceof ArrayNode) {
            $children = $node->getChildren();
            if (empty($children)) {
                return ['type' => 'any'];
            }

            $schema = [
                'type' => 'object',
                'properties' => $this->dumpChildren($node),
            ];
            $enabled = $children['enabled'] ?? null;
            if ($enabled instanceof BooleanNode && $node->hasDefaultValue()) {
                $schema['toggle'] = 'enabled';
            }

            return $schema;
        }

        if ($node instanceof EnumNode) {
            return [
                'type' => 'enum',
                'values' => array_map(self::normalizeValue(...), array_values($node->getValues())),
            ];
        }

        if ($node instanceof IntegerNode || $node instanceof FloatNode) {
            $schema = [
                'type' => $node instanceof IntegerNode ? 'integer' : 'float',
            ];
            if (null !== $min = $node->getMin()) {
                $schema['min'] = $min;
            }
            if (null !== $max = $node->getMax()) {
                $schema['max'] = $max;
            }

            return $schema;
        }

        if ($node instanceof BooleanNode) {
            return ['type' => 'boolean'];
        }

        if ($node instanceof ScalarNode) {
            return ['type' => 'string'];
        }

        return ['type' => 'any'];
    }

    private function dumpChildren(ArrayNode $node): array
    {
        $properties = [];
        foreach ($node->getChildren() as $name => $child) {
            $properties[$name] = $this->dumpNode($child);
        }

        return $properties;
    }

    private static function getMinItems(PrototypedArrayNode $node): int
    {
        return (new \ReflectionProperty(PrototypedArrayNode::class, 'minNumberOfElements'))->getValue($node);
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }
        if (is_array($value)) {
            return array_map(self::normalizeValue(...), $value);
        }

        return $value;
    }

    /**
     * "maxImageSize" → "Max image size", "actions_on_reject" → "Actions on reject".
     */
    public static function humanize(string $key): string
    {
        $words = preg_replace(['/([a-z\d])([A-Z])/', '/[_\-.]+/'], ['$1 $2', ' '], $key);

        return ucfirst(strtolower(trim((string) $words)));
    }
}
