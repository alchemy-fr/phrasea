<?php

declare(strict_types=1);

namespace App\Integration\Config;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * How the configuration form renders a node: declared on the node of the integration
 * configuration tree (see NodeDefinition::attribute()) and exposed by ConfigSchemaDumper.
 */
enum ConfigWidget: string
{
    /** Slug of an attribute definition of the workspace (options: type, multiple, entityList) */
    case Attribute = 'attribute';
    /** Name of a rendition definition of the workspace */
    case Rendition = 'rendition';
    /** ID of a collection of the workspace */
    case Collection = 'collection';
    case Email = 'email';
    /** Language code */
    case Language = 'language';

    final public const string ATTR_WIDGET = 'widget';
    final public const string ATTR_WIDGET_OPTIONS = 'widgetOptions';
    /** The value is a secret: the form masks it */
    final public const string ATTR_SECRET = 'secret';

    /**
     * @template T of NodeDefinition
     *
     * @param T $node
     *
     * @return T
     */
    public static function apply(NodeDefinition $node, self $widget, array $options = []): NodeDefinition
    {
        $node->attribute(self::ATTR_WIDGET, $widget->value);
        if (!empty($options)) {
            $node->attribute(self::ATTR_WIDGET_OPTIONS, $options);
        }

        return $node;
    }
}
