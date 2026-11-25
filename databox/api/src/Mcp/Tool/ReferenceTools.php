<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The reference data the configuration and the search refer to.
 */
final readonly class ReferenceTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_field_types',
        description: 'List the types of the attribute definitions ("type" of create_attribute_definition), with their display name.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listFieldTypes(): array
    {
        return $this->client->getCollection('/field-types');
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_built_in_attributes',
        description: 'List the built-in fields of the assets (@createdAt, @collection, @workspace, @owner…), usable like attribute slugs in the AQL conditions and the sort of search_assets, with their type and whether they are searchable, sortable or faceted.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listBuiltInAttributes(): array
    {
        return $this->client->getCollection('/built-in-attributes');
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_metadata_tags',
        description: 'Find the metadata tags of the files (EXIF, IPTC, XMP…, as read by exiftool) to give to "readFromMetadata" or "writeMetadata" of an attribute definition: the namespaces and tags whose name contains the query, or the tags of a namespace with "<namespace>:<tag prefix>" (e.g. "IPTC:Key"). "writable" tells whether a value can be written into the tag.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listMetadataTags(
        #[Schema(description: 'Namespace or tag name (tags from 2 characters), or "<namespace>:<tag prefix>"; the namespaces only when unset')]
        ?string $query = null,
    ): array {
        return $this->client->getCollection('/metadata-tags', ApiClient::withoutNulls([
            'query' => $query,
        ]));
    }

    /**
     * @return array{items: list<array>, totalItems: int}
     */
    #[McpTool(
        name: 'list_locales',
        description: 'List the locales known to Phrasea (e.g. "fr", "pt_BR"), with their name in English and in the locale itself: the codes to give to the locales of a workspace and to the translations.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listLocales(
        #[Schema(description: 'Only the locales whose code starts with it or whose name contains it, e.g. "pt" or "portug"')]
        ?string $query = null,
    ): array {
        $items = $this->client->getCollection('/locales')['items'];

        if (null !== $query && '' !== $query = trim($query)) {
            $items = array_values(array_filter($items, static fn (array $locale): bool => str_starts_with(strtolower((string) ($locale['id'] ?? '')), strtolower($query))
                || false !== mb_stripos((string) ($locale['name'] ?? ''), $query)
                || false !== mb_stripos((string) ($locale['nativeName'] ?? ''), $query)));
        }

        return ['items' => $items, 'totalItems' => count($items)];
    }
}
