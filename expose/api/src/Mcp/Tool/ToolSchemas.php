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

    /**
     * Subtitles of an asset (Asset::$webVTT).
     */
    final public const array WEBVTT = [
        'type' => ['array', 'null'],
        'description' => 'Subtitle tracks (WebVTT), replacing the current ones; [] removes them all',
        'items' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'Track ID (see webVTTLinks in get_asset), to keep the link of an existing track; generated when unset'],
                'label' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100, 'description' => 'Label shown in the player'],
                'locale' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language, e.g. "fr"'],
                'kind' => ['type' => 'string', 'description' => 'Track kind, e.g. "subtitles" or "captions"'],
                'content' => ['type' => 'string', 'description' => 'WebVTT content, starting with "WEBVTT"'],
            ],
            'required' => ['label', 'locale', 'content'],
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
            'includeDownloadTermsInArchive' => ['type' => 'boolean', 'description' => 'Add the download terms to the archive of the publication (needs a download terms URL)'],
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
