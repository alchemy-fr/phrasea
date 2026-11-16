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
    /**
     * Client ID of a Keycloak OAuth client (public, standard flow) authorizing the users of the
     * integration: the form offers to pick one or to create one when the user's Keycloak account can.
     */
    case OAuthClient = 'oauth_client';

    final public const string ATTR_WIDGET = 'widget';
    final public const string ATTR_WIDGET_OPTIONS = 'widgetOptions';
    /** The value is a secret: the form masks it */
    final public const string ATTR_SECRET = 'secret';
    /**
     * The value is a URL (an endpoint the integration calls, a link given to the users), a
     * first-level key only: in a workspace integration, a value other than the default refers
     * to no secret nor variable, and disables the instance variables (see IntegrationConfig).
     */
    final public const string ATTR_URL = 'url';
    /**
     * The API reads the value itself (e.g. the key a webhook compares to its requests), a
     * first-level key only: it cannot refer to a secret, which only the workers decrypt
     * (see IntegrationConfig::validateReferences()).
     */
    final public const string ATTR_READ_BY_API = 'readByApi';

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
