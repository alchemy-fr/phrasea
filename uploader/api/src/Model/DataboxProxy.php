<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Api\Provider\DataboxProxyProvider;
use App\Api\Provider\FormFieldOptionsProvider;

/**
 * Reads Databox on behalf of the user: the catalog the form builder picks fields from,
 * and the values of the entity and tag fields of a form.
 * The providers check the access before calling Databox.
 */
#[ApiResource(
    shortName: 'databox',
    operations: [
        new Get(
            uriTemplate: '/databox/workspaces',
            name: self::WORKSPACES,
            provider: DataboxProxyProvider::class,
        ),
        new Get(
            uriTemplate: '/databox/workspaces/{id}',
            name: self::WORKSPACE,
            provider: DataboxProxyProvider::class,
        ),
        new Get(
            uriTemplate: '/databox/workspaces/{id}/fields',
            name: self::WORKSPACE_FIELDS,
            provider: DataboxProxyProvider::class,
        ),
        new Get(
            uriTemplate: '/databox/workspaces/{id}/tags',
            name: self::WORKSPACE_TAGS,
            provider: DataboxProxyProvider::class,
        ),
        new Get(
            uriTemplate: '/databox/targets/{id}/destination',
            name: self::TARGET_DESTINATION,
            provider: DataboxProxyProvider::class,
        ),
        new Get(
            uriTemplate: '/databox/entity-lists/{id}/values',
            name: self::ENTITY_LIST_VALUES,
            provider: DataboxProxyProvider::class,
        ),
        new Get(
            uriTemplate: '/targets/{id}/form-fields/{key}/options',
            uriVariables: ['id', 'key'],
            name: self::FORM_FIELD_OPTIONS,
            provider: FormFieldOptionsProvider::class,
        ),
    ],
)]
final class DataboxProxy
{
    public const string WORKSPACES = 'databox_workspaces';
    public const string WORKSPACE = 'databox_workspace';
    public const string WORKSPACE_FIELDS = 'databox_workspace_fields';
    public const string WORKSPACE_TAGS = 'databox_workspace_tags';
    public const string TARGET_DESTINATION = 'databox_target_destination';
    public const string ENTITY_LIST_VALUES = 'databox_entity_list_values';
    public const string FORM_FIELD_OPTIONS = 'form_field_options';
}
