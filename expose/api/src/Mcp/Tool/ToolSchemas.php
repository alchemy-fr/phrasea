<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

/**
 * JSON Schemas of the tool arguments shared by several tools.
 */
final class ToolSchemas
{
    final public const array TRANSLATIONS = [
        'type' => ['object', 'null'],
        'description' => 'Translations of the title and description by locale, e.g. {"fr": {"title": "…", "description": "…"}}',
        'additionalProperties' => [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ],
        ],
    ];

    private const array TERMS = [
        'type' => 'object',
        'properties' => [
            'enabled' => ['type' => 'boolean'],
            'text' => ['type' => 'string', 'description' => 'Terms text (HTML allowed)'],
            'url' => ['type' => 'string', 'description' => 'URL of external terms'],
        ],
    ];

    /**
     * PublicationConfig, shared by publications and profiles.
     */
    final public const array CONFIG = [
        'type' => ['object', 'null'],
        'description' => 'Publication settings. Every key is optional; on a publication an unset (null) value inherits the one of its profile.',
        'properties' => [
            'enabled' => ['type' => 'boolean', 'description' => 'Whether the publication is online'],
            'publiclyListed' => ['type' => 'boolean', 'description' => 'Whether the publication appears in the public list'],
            'layout' => ['type' => 'string', 'enum' => ['gallery', 'grid', 'mapbox', 'download'], 'description' => 'Display layout'],
            'theme' => ['type' => 'string', 'description' => 'Client theme name'],
            'css' => ['type' => 'string', 'description' => 'Custom CSS'],
            'downloadEnabled' => ['type' => 'boolean'],
            'downloadViaEmail' => ['type' => 'boolean', 'description' => 'Send download links by email instead of direct download'],
            'includeDownloadTermsInZippy' => ['type' => 'boolean'],
            'beginsAt' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Visible from this date (ISO 8601)'],
            'expiresAt' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Visible until this date (ISO 8601)'],
            'securityMethod' => ['type' => ['string', 'null'], 'enum' => [null, 'password', 'authentication'], 'description' => 'null = public, "password" = shared password, "authentication" = signed-in users granted by ACL'],
            'securityOptions' => ['type' => 'object', 'description' => 'For the "password" method: {"password": "..."}'],
            'terms' => self::TERMS + ['description' => 'Terms to accept before viewing'],
            'downloadTerms' => self::TERMS + ['description' => 'Terms to accept before downloading'],
            'mapOptions' => [
                'type' => 'object',
                'properties' => [
                    'lat' => ['type' => 'number'],
                    'lng' => ['type' => 'number'],
                    'zoom' => ['type' => 'integer'],
                    'mapLayout' => ['type' => 'string'],
                ],
            ],
            'layoutOptions' => [
                'type' => 'object',
                'properties' => [
                    'displayMap' => ['type' => 'boolean'],
                    'displayMapPins' => ['type' => 'boolean'],
                    'logoUrl' => ['type' => 'string'],
                ],
            ],
        ],
    ];
}
