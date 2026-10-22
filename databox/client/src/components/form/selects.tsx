'use client';

import {getUserDisplayName} from '@/lib/users';
import {useCallback, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {AsyncCombobox, ComboOption, ComboPage} from './AsyncCombobox';
import {
    getAttributeDefinitionsPage,
    getAttributeEntities,
    getAttributeEntity,
    getEntityList,
    getEntityLists,
    getTag,
    getTags,
    postAttributeEntity,
} from '@/lib/api/metadata';
import {
    getGroups,
    getRenditionDefinition,
    getRenditionDefinitions,
    getUsers,
} from '@/lib/api/misc';
import {
    EntityName,
    type AssetType,
    type AttributeDefinition,
    type AttributeEntity,
    type EntityList,
    type Group,
    type Page,
    type RenditionDefinition,
    type Tag,
    type User,
    type Workspace,
} from '@/types/api';
import {TagChip, EntityChip} from '@/components/chips';
import {entityLabel} from '@/features/attributes/types/registry';
import {iri, idFromIri} from '@/lib/utils/iri';
import {
    normalizeDefinition,
    useDefinitionsStore,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import type {AttributeDefinitionFilters} from '@/features/attributes/useAttributeDefinitionList';
import {WorkspaceName} from '@/features/collections/WorkspaceName';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {getWorkspaces} from '@/lib/api/collections';

function comboPage<T>(
    page: Page<T>,
    toOption: (item: T) => ComboOption<T>
): ComboPage<T> {
    return {options: page.items.map(toOption), next: page.next};
}

/** Page size of the Keycloak users and groups, paged by offset */
const PERMISSION_PAGE = 30;
/** The most ids `/permissions/users|groups` resolves at once */
const PERMISSION_IDS = 50;

/**
 * A page of users or groups: the cursor is the offset of the next page,
 * which may exist as long as a page is full.
 */
async function permissionPage<T>(
    list: (offset: number) => Promise<T[]>,
    toOption: (item: T) => ComboOption<T>,
    cursor?: string
): Promise<ComboPage<T>> {
    const offset = Number(cursor ?? 0);
    const items = await list(offset);

    return {
        options: items.map(toOption),
        next:
            items.length === PERMISSION_PAGE
                ? String(offset + PERMISSION_PAGE)
                : undefined,
    };
}

async function resolveByIds<T>(
    ids: string[],
    get: (ids: string[]) => Promise<T[]>,
    toOption: (item: T) => ComboOption<T>
): Promise<ComboOption<T>[]> {
    const chunks: string[][] = [];
    for (let i = 0; i < ids.length; i += PERMISSION_IDS) {
        chunks.push(ids.slice(i, i + PERMISSION_IDS));
    }

    return (await Promise.all(chunks.map(get))).flat().map(toOption);
}

function tagOption(tag: Tag, useIri: boolean): ComboOption<Tag> {
    return {
        value: useIri ? tag['@id'] : tag.id,
        label: tag.displayName ?? tag.name,
        render: <TagChip tag={tag} size="xs" />,
        item: tag,
    };
}

type TagSelectProps = {
    workspaceId?: string;
    /** store IRIs instead of ids */
    useIri?: boolean;
    disabled?: boolean;
    id?: string;
} & (
    | {
          multiple: true;
          value: string[];
          onChange: (value: string[], tags: Tag[]) => void;
      }
    | {
          multiple?: false;
          value: string | undefined;
          onChange: (value: string | undefined, tag?: Tag) => void;
      }
);

export function TagSelect(props: TagSelectProps) {
    const {t} = useTranslation();
    const {workspaceId, useIri = false, disabled, id} = props;
    const load = useCallback(
        async (query: string, signal: AbortSignal, cursor?: string) =>
            comboPage(
                await getTags(
                    {
                        workspace: workspaceId
                            ? iri(EntityName.Workspace, workspaceId)
                            : undefined,
                        query: query || undefined,
                        url: cursor,
                    },
                    signal
                ),
                tg => tagOption(tg, useIri)
            ),
        [workspaceId, useIri]
    );
    const resolve = useCallback(
        async (value: string) =>
            tagOption(await getTag(idFromIri(value)), useIri),
        [useIri]
    );

    if (props.multiple) {
        return (
            <AsyncCombobox<Tag>
                id={id}
                multiple
                disabled={disabled}
                queryKey={['tags', workspaceId, useIri]}
                loadPage={load}
                resolveValue={resolve}
                value={props.value}
                onChange={(v, opts) =>
                    props.onChange(v, opts.map(o => o.item!).filter(Boolean))
                }
                placeholder={t('form.tags.placeholder', 'Select tags…')}
            />
        );
    }

    return (
        <AsyncCombobox<Tag>
            id={id}
            disabled={disabled}
            queryKey={['tags', workspaceId, useIri]}
            loadPage={load}
            resolveValue={resolve}
            value={props.value}
            onChange={(v, opt) => props.onChange(v, opt?.item)}
            placeholder={t('form.tags.placeholder_single', 'Select a tag…')}
        />
    );
}

function userOption(user: User): ComboOption<User> {
    return {value: user.id, label: getUserDisplayName(user), item: user};
}

function groupOption(group: Group): ComboOption<Group> {
    return {value: group.id, label: group.name, item: group};
}

export function UserSelect({
    value,
    onChange,
    disabled,
    id,
    multiple,
}: {
    value: string | string[] | undefined;
    onChange: (value: any, users?: User[]) => void;
    disabled?: boolean;
    id?: string;
    multiple?: boolean;
}) {
    const {t} = useTranslation();
    const load = useCallback(
        (query: string, signal: AbortSignal, cursor?: string) =>
            permissionPage(
                offset =>
                    getUsers({query, offset, limit: PERMISSION_PAGE}, signal),
                userOption,
                cursor
            ),
        []
    );
    const resolve = useCallback(
        (ids: string[]) =>
            resolveByIds(ids, chunk => getUsers({ids: chunk}), userOption),
        []
    );

    if (multiple) {
        return (
            <AsyncCombobox<User>
                id={id}
                multiple
                disabled={disabled}
                queryKey={['users']}
                loadPage={load}
                resolveValues={resolve}
                value={(value as string[]) ?? []}
                onChange={(v, opts) =>
                    onChange(v, opts.map(o => o.item!).filter(Boolean))
                }
                placeholder={t('form.users.placeholder', 'Select users…')}
            />
        );
    }

    return (
        <AsyncCombobox<User>
            id={id}
            disabled={disabled}
            queryKey={['users']}
            loadPage={load}
            resolveValues={resolve}
            value={value as string | undefined}
            onChange={(v, opt) =>
                onChange(v, opt?.item ? [opt.item] : undefined)
            }
            placeholder={t('form.users.placeholder_single', 'Select a user…')}
        />
    );
}

export function GroupSelect({
    value,
    onChange,
    disabled,
    id,
    multiple,
}: {
    value: string | string[] | undefined;
    onChange: (value: any, groups?: Group[]) => void;
    disabled?: boolean;
    id?: string;
    multiple?: boolean;
}) {
    const {t} = useTranslation();
    const load = useCallback(
        (query: string, signal: AbortSignal, cursor?: string) =>
            permissionPage(
                offset =>
                    getGroups({query, offset, limit: PERMISSION_PAGE}, signal),
                groupOption,
                cursor
            ),
        []
    );
    const resolve = useCallback(
        (ids: string[]) =>
            resolveByIds(ids, chunk => getGroups({ids: chunk}), groupOption),
        []
    );

    if (multiple) {
        return (
            <AsyncCombobox<Group>
                id={id}
                multiple
                disabled={disabled}
                queryKey={['groups']}
                loadPage={load}
                resolveValues={resolve}
                value={(value as string[]) ?? []}
                onChange={(v, opts) =>
                    onChange(v, opts.map(o => o.item!).filter(Boolean))
                }
                placeholder={t('form.groups.placeholder', 'Select groups…')}
            />
        );
    }

    return (
        <AsyncCombobox<Group>
            id={id}
            disabled={disabled}
            queryKey={['groups']}
            loadPage={load}
            resolveValues={resolve}
            value={value as string | undefined}
            onChange={(v, opt) =>
                onChange(v, opt?.item ? [opt.item] : undefined)
            }
            placeholder={t('form.groups.placeholder_single', 'Select a group…')}
        />
    );
}

function entityOption(e: AttributeEntity): ComboOption<AttributeEntity> {
    return {
        value: e.id,
        label: entityLabel(e),
        render: <EntityChip entity={e} size="sm" />,
        item: e,
    };
}

export function AttributeEntitySelect({
    listId,
    allowNewValues,
    value,
    onChange,
    disabled,
    id,
}: {
    listId: string | undefined;
    allowNewValues?: boolean;
    value: string | undefined;
    onChange: (value: string | undefined, entity?: AttributeEntity) => void;
    disabled?: boolean;
    id?: string;
}) {
    const {t} = useTranslation();
    const load = useCallback(
        async (query: string, _signal: AbortSignal, cursor?: string) =>
            comboPage(
                await getAttributeEntities({
                    list: listId,
                    query: query || undefined,
                    url: cursor,
                }),
                entityOption
            ),
        [listId]
    );
    const resolve = useCallback(
        async (value: string) => entityOption(await getAttributeEntity(value)),
        []
    );

    return (
        <AsyncCombobox<AttributeEntity>
            id={id}
            disabled={disabled || !listId}
            queryKey={['attribute-entities', listId]}
            loadPage={load}
            resolveValue={resolve}
            value={value}
            onChange={(v, opt) => onChange(v, opt?.item)}
            placeholder={t('form.entity.placeholder', 'Select a value…')}
            onCreate={
                allowNewValues && listId
                    ? async query =>
                          entityOption(
                              await postAttributeEntity(listId, {value: query})
                          )
                    : undefined
            }
        />
    );
}

function entityListOption(
    list: EntityList,
    useIri: boolean
): ComboOption<EntityList> {
    return {
        value: useIri ? list['@id'] : list.id,
        label: list.name,
        item: list,
    };
}

/**
 * Entity list of a workspace, searched by name.
 */
export function EntityListSelect({
    workspaceId,
    useIri = false,
    value,
    onChange,
    fixedOptions,
    placeholder,
    disabled,
    id,
}: {
    workspaceId: string;
    /** store IRIs instead of ids */
    useIri?: boolean;
    value: string | undefined;
    onChange: (value: string | undefined, list?: EntityList) => void;
    /** Choices listed before the lists, whatever the search */
    fixedOptions?: ComboOption<EntityList>[];
    placeholder?: string;
    disabled?: boolean;
    id?: string;
}) {
    const {t} = useTranslation();
    const load = useCallback(
        async (query: string, signal: AbortSignal, cursor?: string) =>
            comboPage(
                await getEntityLists(
                    {workspaceId, query: query || undefined, url: cursor},
                    signal
                ),
                l => entityListOption(l, useIri)
            ),
        [workspaceId, useIri]
    );
    const resolve = useCallback(
        async (v: string) =>
            fixedOptions?.find(o => o.value === v) ??
            entityListOption(await getEntityList(idFromIri(v)), useIri),
        [useIri, fixedOptions]
    );

    return (
        <AsyncCombobox<EntityList>
            id={id}
            disabled={disabled}
            queryKey={['entity-lists', workspaceId, 'select', useIri]}
            loadPage={load}
            resolveValue={resolve}
            fixedOptions={fixedOptions}
            value={value}
            onChange={(v, opt) => onChange(v, opt?.item)}
            placeholder={
                placeholder ??
                t('form.entity_list.placeholder', 'Select a list…')
            }
        />
    );
}

function renditionOption(
    d: RenditionDefinition,
    useIri: boolean
): ComboOption<RenditionDefinition> {
    return {
        value: useIri ? d['@id'] : d.id,
        label: d.displayName ?? d.name,
        item: d,
    };
}

type RenditionDefinitionSelectProps = {
    workspaceId: string;
    /** store IRIs instead of ids */
    useIri?: boolean;
    target?: AssetType;
    /** Leaves out the renditions the asset policies hide on this asset */
    assetId?: string;
    substitutableOnly?: boolean;
    /** Id of a definition left out (e.g. the one being edited) */
    exclude?: string;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
} & (
    | {
          multiple: true;
          value: string[];
          onChange: (
              value: string[],
              definitions: RenditionDefinition[]
          ) => void;
      }
    | {
          multiple?: false;
          value: string | undefined;
          onChange: (
              value: string | undefined,
              definition?: RenditionDefinition
          ) => void;
      }
);

/**
 * Rendition definition of a workspace, by id or IRI, searched by name.
 * Where the name is what is stored, see `RenditionPicker`.
 */
export function RenditionDefinitionSelect(
    props: RenditionDefinitionSelectProps
) {
    const {t} = useTranslation();
    const {
        workspaceId,
        useIri = false,
        target,
        assetId,
        substitutableOnly,
        exclude,
        id,
        disabled,
    } = props;
    const load = useCallback(
        async (query: string, signal: AbortSignal, cursor?: string) => {
            const page = await getRenditionDefinitions(
                {
                    workspaceId,
                    assetId,
                    target,
                    query: query || undefined,
                    url: cursor,
                },
                signal
            );

            return {
                options: page.items
                    .filter(
                        d =>
                            d.id !== exclude &&
                            (!substitutableOnly || d.substitutable)
                    )
                    .map(d => renditionOption(d, useIri)),
                next: page.next,
            };
        },
        [workspaceId, assetId, target, substitutableOnly, exclude, useIri]
    );
    const resolve = useCallback(
        async (v: string) =>
            renditionOption(await getRenditionDefinition(idFromIri(v)), useIri),
        [useIri]
    );
    const shared = {
        id,
        disabled,
        queryKey: [
            'rendition-definitions',
            'select',
            workspaceId,
            {assetId, target, substitutableOnly, exclude, useIri},
        ],
        loadPage: load,
        resolveValue: resolve,
        placeholder:
            props.placeholder ??
            t('form.rendition.placeholder', 'Select a rendition…'),
    };

    if (props.multiple) {
        const {onChange} = props;

        return (
            <AsyncCombobox<RenditionDefinition>
                {...shared}
                multiple
                value={props.value}
                onChange={(v, opts) =>
                    onChange(v, opts.map(o => o.item!).filter(Boolean))
                }
            />
        );
    }
    const {onChange} = props;

    return (
        <AsyncCombobox<RenditionDefinition>
            {...shared}
            value={props.value}
            onChange={(v, opt) => onChange(v, opt?.item)}
        />
    );
}

function attributeDefinitionOption(
    d: AttributeDefinition,
    valueKey: 'id' | 'slug',
    showWorkspace: boolean
): ComboOption<AttributeDefinition> {
    const label = d.displayName ?? d.name;
    const workspaceId = workspaceIdOf(d);

    return {
        value: valueKey === 'slug' ? d.slug : d.id,
        label,
        render:
            showWorkspace && workspaceId ? (
                <span className="flex items-center gap-1.5">
                    <span className="truncate">{label}</span>
                    <span className="truncate text-xs text-muted-foreground">
                        <WorkspaceName id={workspaceId} />
                    </span>
                </span>
            ) : undefined,
        item: d,
    };
}

/**
 * Attribute definition, by id or slug, searched by name or slug: of a
 * workspace, or of all of them (a slug shared by several workspaces is then
 * listed once).
 */
export function AttributeDefinitionSelect({
    workspaceId,
    filters,
    valueKey = 'id',
    postFilter,
    showWorkspace = false,
    value,
    onChange,
    placeholder,
    disabled,
    id,
}: {
    workspaceId?: string;
    filters?: AttributeDefinitionFilters;
    valueKey?: 'id' | 'slug';
    /** Leaves out definitions of the loaded pages */
    postFilter?: (definition: AttributeDefinition) => boolean;
    /** Names the workspace of each definition */
    showWorkspace?: boolean;
    value: string | undefined;
    onChange: (
        value: string | undefined,
        definition?: AttributeDefinition
    ) => void;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
}) {
    const {t} = useTranslation();
    const filterKey = JSON.stringify(filters ?? {});
    const postFilterRef = useRef(postFilter);
    postFilterRef.current = postFilter;
    const load = useCallback(
        async (query: string, signal: AbortSignal, cursor?: string) => {
            const page = await getAttributeDefinitionsPage(
                {
                    ...(JSON.parse(filterKey) as AttributeDefinitionFilters),
                    workspaceId,
                    query: query || undefined,
                    url: cursor,
                },
                signal
            );
            const items = page.items.map(normalizeDefinition);
            useDefinitionsStore.getState().ingest(items);

            return comboPage(
                {
                    ...page,
                    items: items.filter(
                        d => !postFilterRef.current || postFilterRef.current(d)
                    ),
                },
                d => attributeDefinitionOption(d, valueKey, showWorkspace)
            );
        },
        [workspaceId, filterKey, valueKey, showWorkspace]
    );
    const resolve = useCallback(
        async (values: string[]) => {
            const store = useDefinitionsStore.getState();
            await store.ensure(
                valueKey === 'slug' ? {slugs: values} : {ids: values}
            );
            const known = Object.values(useDefinitionsStore.getState().byId);

            return values
                .map(v =>
                    known.find(
                        d =>
                            (valueKey === 'slug' ? d.slug : d.id) === v &&
                            (!workspaceId || workspaceIdOf(d) === workspaceId)
                    )
                )
                .filter((d): d is AttributeDefinition => !!d)
                .map(d =>
                    attributeDefinitionOption(d, valueKey, showWorkspace)
                );
        },
        [valueKey, workspaceId, showWorkspace]
    );

    return (
        <AsyncCombobox<AttributeDefinition>
            id={id}
            disabled={disabled}
            queryKey={[
                'attribute-definitions',
                'select',
                workspaceId,
                filterKey,
                valueKey,
            ]}
            loadPage={load}
            resolveValues={resolve}
            value={value}
            onChange={(v, opt) => onChange(v, opt?.item)}
            placeholder={
                placeholder ??
                t('form.attribute.placeholder', 'Select an attribute…')
            }
        />
    );
}

function workspaceOption(w: Workspace): ComboOption<Workspace> {
    return {value: w.id, label: w.displayName ?? w.name, item: w};
}

/** Workspace by id, searched by name */
export function WorkspaceSelect({
    value,
    onChange,
    placeholder,
    disabled,
    id,
    className,
}: {
    value: string | undefined;
    onChange: (value: string | undefined, workspace?: Workspace) => void;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
    className?: string;
}) {
    const load = useCallback(
        async (query: string, signal: AbortSignal, cursor?: string) =>
            comboPage(
                await getWorkspaces(
                    {query: query || undefined, url: cursor},
                    signal
                ),
                workspaceOption
            ),
        []
    );
    const resolve = useCallback(async (ids: string[]) => {
        await useCollectionStore.getState().ensureWorkspaces(ids);
        const byId = useCollectionStore.getState().workspacesById;

        return ids
            .map(i => byId[i])
            .filter((w): w is Workspace => !!w)
            .map(workspaceOption);
    }, []);

    return (
        <AsyncCombobox<Workspace>
            id={id}
            disabled={disabled}
            className={className}
            queryKey={['workspaces', 'select']}
            loadPage={load}
            resolveValues={resolve}
            value={value}
            onChange={(v, opt) => onChange(v, opt?.item)}
            placeholder={placeholder}
        />
    );
}
