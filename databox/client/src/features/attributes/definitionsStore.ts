import {create} from 'zustand';
import {useEffect, useMemo} from 'react';
import {
    AssetTypeFilter,
    AttributeDefinition,
    AttributeDefinitionOrBuiltIn,
    AttributeType,
    BuiltInAttribute,
} from '@/types/api';
import {
    getAttributeDefinitionsPage,
    getBuiltInAttributes,
} from '@/lib/api/metadata';
import {fetchAllPages} from '@/lib/api/hydra';
import {BuiltInAttribute as BuiltInEnum} from '@/features/search/searchState';
import {getFileFamily} from '@/lib/utils/mime';

export type DefinitionsIndex<
    T extends AttributeDefinitionOrBuiltIn = AttributeDefinitionOrBuiltIn,
> = Record<string, T>;

export type DefinitionKeys = {ids?: string[]; slugs?: string[]};

/**
 * A cache of the definitions met so far: the pages of the lists (see
 * `useAttributeDefinitionList`) feed it, the missing ones are resolved by key
 * (`ensure`). It never holds the whole list.
 */
type State = {
    byId: Record<string, AttributeDefinition>;
    builtIn: BuiltInAttribute[];
    builtInLoaded: boolean;
    /** Keys already asked for (found or not): `id:<uuid>`, `slug:<slug>` */
    resolved: Record<string, true>;
    loadBuiltIn: () => Promise<void>;
    /** Stores the definitions of a loaded page */
    ingest: (definitions: AttributeDefinition[]) => void;
    /**
     * Loads the definitions of these ids and slugs not resolved yet: the keys
     * asked for during the same tick are loaded together.
     */
    ensure: (keys: DefinitionKeys) => Promise<void>;
    upsertDefinition: (definition: AttributeDefinition) => void;
    removeDefinition: (id: string) => void;
    /** Resolves again the definitions of the workspace (and every slug) */
    invalidateWorkspace: (workspaceId: string) => void;
};

/** The most keys sent in a request */
const ENSURE_CHUNK = 50;
const ENSURE_PAGE_SIZE = 100;

export function normalizeDefinition(
    d: AttributeDefinition
): AttributeDefinition {
    return {
        ...d,
        searchSlug: d.searchSlug ?? d.slug,
        enabled: d.enabled ?? true,
    };
}

/**
 * The API exposes built-ins with their AQL key as `id` (`@workspace`), no
 * `slug`/`searchSlug`, and a raw ES type (`keyword`): derive the slugs from the
 * key and restore the entity type so values get resolved (workspace, user...).
 */
function normalizeBuiltIn(d: BuiltInAttribute): BuiltInAttribute {
    const slug = d.slug ?? d.id;

    return {
        ...d,
        builtIn: true,
        slug,
        searchSlug: d.searchSlug ?? slug,
        type: builtInTypes[slug as BuiltInEnum] ?? d.type,
        enabled: true,
    };
}

const idKey = (id: string) => `id:${id}`;
const slugKey = (slug: string) => `slug:${slug}`;

/** Built-in keys (`@createdAt`) are not attribute definitions */
const isDefinitionKey = (key: string | undefined): key is string =>
    !!key && !key.startsWith('@');

/**
 * Slug of an attribute from its search slug: `<slug>_<type>_<m|s>`, the type
 * having its `_` turned into `-`, possibly followed by a locale for the
 * translatable facets (`description_text_s_fr`). Nothing for built-ins.
 */
export function slugOfSearchSlug(name: string): string | undefined {
    if (!isDefinitionKey(name)) {
        return undefined;
    }

    return name.replace(/_[a-z]{2}$/, '').match(/^(.+)_[^_]+_[ms]$/)?.[1];
}

let builtInLoading: Promise<void> | undefined;
type Batch = {ids: Set<string>; slugs: Set<string>; done: Promise<void>};
let batch: Batch | undefined;
const inflight = new Map<string, Promise<void>>();

function chunks(values: string[]): string[][] {
    const list: string[][] = [];
    for (let i = 0; i < values.length; i += ENSURE_CHUNK) {
        list.push(values.slice(i, i + ENSURE_CHUNK));
    }

    return list;
}

export const useDefinitionsStore = create<State>((set, get) => {
    const resolveChunk = async (
        kind: 'ids' | 'slugs',
        values: string[]
    ): Promise<void> => {
        const keys = values.map(kind === 'ids' ? idKey : slugKey);
        try {
            const definitions = (
                await fetchAllPages(
                    getAttributeDefinitionsPage({
                        [kind]: values,
                        limit: ENSURE_PAGE_SIZE,
                    }),
                    url => getAttributeDefinitionsPage({url})
                )
            ).map(normalizeDefinition);
            set(s => {
                const byId = {...s.byId};
                const asked = new Set(values);
                // The definitions not found anymore are gone
                Object.values(byId).forEach(d => {
                    if (asked.has(kind === 'ids' ? d.id : d.slug)) {
                        delete byId[d.id];
                    }
                });
                const resolved = {...s.resolved};
                definitions.forEach(d => {
                    byId[d.id] = d;
                    resolved[idKey(d.id)] = true;
                });
                keys.forEach(k => {
                    resolved[k] = true;
                });

                return {byId, resolved};
            });
        } catch (e) {
            console.warn('Failed to load attribute definitions', e);
        } finally {
            keys.forEach(k => inflight.delete(k));
        }
    };

    const flush = async (ids: Set<string>, slugs: Set<string>) => {
        await Promise.all([
            ...chunks([...ids]).map(c => resolveChunk('ids', c)),
            ...chunks([...slugs]).map(c => resolveChunk('slugs', c)),
        ]);
    };

    return {
        byId: {},
        builtIn: [],
        builtInLoaded: false,
        resolved: {},

        loadBuiltIn: () => {
            if (get().builtInLoaded) {
                return Promise.resolve();
            }
            builtInLoading ??= (async () => {
                try {
                    const builtIn = await getBuiltInAttributes();
                    set({
                        builtIn: builtIn.map(normalizeBuiltIn),
                        builtInLoaded: true,
                    });
                } catch (e) {
                    // Callers fire-and-forget: never leave an unhandled rejection.
                    console.warn('Failed to load built-in attributes', e);
                } finally {
                    builtInLoading = undefined;
                }
            })();

            return builtInLoading;
        },

        ingest: definitions => {
            if (definitions.length === 0) {
                return;
            }
            set(s => {
                const byId = {...s.byId};
                const resolved = {...s.resolved};
                definitions.forEach(d => {
                    byId[d.id] = normalizeDefinition(d);
                    resolved[idKey(d.id)] = true;
                });

                return {byId, resolved};
            });
        },

        ensure: ({ids = [], slugs = []}) => {
            const {resolved} = get();
            const waits = new Set<Promise<void>>();
            const add = (kind: 'ids' | 'slugs', value: string) => {
                const key = kind === 'ids' ? idKey(value) : slugKey(value);
                if (resolved[key]) {
                    return;
                }
                const pending = inflight.get(key);
                if (pending) {
                    waits.add(pending);

                    return;
                }
                if (!batch) {
                    const current = {
                        ids: new Set<string>(),
                        slugs: new Set<string>(),
                    } as Batch;
                    // Sent once the current tick is over
                    current.done = Promise.resolve().then(() => {
                        batch = undefined;

                        return flush(current.ids, current.slugs);
                    });
                    batch = current;
                }
                batch[kind].add(value);
                inflight.set(key, batch.done);
                waits.add(batch.done);
            };
            ids.filter(isDefinitionKey).forEach(id => add('ids', id));
            slugs.filter(isDefinitionKey).forEach(s => add('slugs', s));

            return Promise.all(waits).then(() => undefined);
        },

        upsertDefinition: definition =>
            set(s => ({
                byId: {
                    ...s.byId,
                    [definition.id]: normalizeDefinition(definition),
                },
                resolved: {...s.resolved, [idKey(definition.id)]: true},
            })),

        removeDefinition: id =>
            set(s => {
                const {[id]: _removed, ...byId} = s.byId;

                return {byId};
            }),

        invalidateWorkspace: workspaceId =>
            set(s => {
                const resolved: Record<string, true> = {};
                Object.keys(s.resolved).forEach(k => {
                    if (k.startsWith('slug:')) {
                        return;
                    }
                    const d = s.byId[k.slice(3)];
                    if (d && workspaceIdOf(d) === workspaceId) {
                        return;
                    }
                    resolved[k] = true;
                });

                return {resolved};
            }),
    };
});

/** Seeds the store, the given definitions being resolved (tests) */
export function seedDefinitions({
    definitions = [],
    builtIn,
}: {
    definitions?: AttributeDefinition[];
    builtIn?: BuiltInAttribute[];
}) {
    const byId: Record<string, AttributeDefinition> = {};
    const resolved: Record<string, true> = {};
    definitions.map(normalizeDefinition).forEach(d => {
        byId[d.id] = d;
        resolved[idKey(d.id)] = true;
        resolved[slugKey(d.slug)] = true;
    });
    useDefinitionsStore.setState({
        byId,
        resolved,
        ...(builtIn
            ? {builtIn: builtIn.map(normalizeBuiltIn), builtInLoaded: true}
            : {}),
    });
}

export function workspaceIdOf(
    definition: AttributeDefinition
): string | undefined {
    const ws = definition.workspace;

    return typeof ws === 'string' ? ws.split('/').pop() : ws?.id;
}

/**
 * Resolves the given keys (once each, see `ensure`), and the built-ins when
 * asked: again after an invalidation.
 */
export function useEnsureDefinitions(
    {ids, slugs}: DefinitionKeys,
    builtIn = false
): void {
    const ensure = useDefinitionsStore(s => s.ensure);
    const loadBuiltIn = useDefinitionsStore(s => s.loadBuiltIn);
    const builtInLoaded = useDefinitionsStore(s => s.builtInLoaded);
    // A string: a stable snapshot whatever the keys given
    const missing = useDefinitionsStore(s =>
        [
            ...(ids ?? [])
                .filter(isDefinitionKey)
                .filter(id => !s.resolved[idKey(id)])
                .map(idKey),
            ...(slugs ?? [])
                .filter(isDefinitionKey)
                .filter(slug => !s.resolved[slugKey(slug)])
                .map(slugKey),
        ]
            .filter((k, i, all) => all.indexOf(k) === i)
            .join('\n')
    );

    useEffect(() => {
        if (!missing) {
            return;
        }
        const keys = missing.split('\n');
        void ensure({
            ids: keys.filter(k => k.startsWith('id:')).map(k => k.slice(3)),
            slugs: keys.filter(k => k.startsWith('slug:')).map(k => k.slice(5)),
        });
    }, [missing, ensure]);

    useEffect(() => {
        if (builtIn && !builtInLoaded) {
            void loadBuiltIn();
        }
    }, [builtIn, builtInLoaded, loadBuiltIn]);
}

type IndexOptions = {
    workspaceId?: string;
    target?: AssetTypeFilter;
    includeBuiltIn?: boolean;
};

function keep(
    d: AttributeDefinition,
    {workspaceId, target = AssetTypeFilter.All}: IndexOptions
): boolean {
    if (workspaceId && workspaceIdOf(d) !== workspaceId) {
        return false;
    }

    return !(target && d.target && (d.target & target) === 0);
}

/** See `useDefinitionsBySearchSlug` */
export function indexBySearchSlug(
    state: Pick<State, 'byId' | 'builtIn'>,
    options: IndexOptions = {}
): DefinitionsIndex {
    const index: DefinitionsIndex = {};
    if (options.includeBuiltIn ?? true) {
        state.builtIn.forEach(b => {
            index[b.searchSlug] = b;
        });
    }
    Object.values(state.byId).forEach(d => {
        if (d.enabled && keep(d, options)) {
            index[d.searchSlug] = d;
        }
    });

    return index;
}

/** See `useDefinitionsBySlug` */
export function indexBySlug(
    state: Pick<State, 'byId' | 'builtIn'>,
    options: IndexOptions = {}
): DefinitionsIndex {
    const index: DefinitionsIndex = {};
    Object.values(indexBySearchSlug(state, options)).forEach(d => {
        index[aqlKey(d)] = d;
    });

    return index;
}

/** See `useDefinitionsById` */
export function indexById(
    state: Pick<State, 'byId' | 'builtIn'>,
    options: IndexOptions = {}
): DefinitionsIndex {
    const index: DefinitionsIndex = {};
    if (options.includeBuiltIn ?? true) {
        state.builtIn.forEach(b => {
            index[b.searchSlug] = b;
        });
    }
    Object.values(state.byId).forEach(d => {
        if (keep(d, options)) {
            index[d.id] = d;
        }
    });

    return index;
}

function useIndex(
    build: typeof indexById,
    options: IndexOptions
): DefinitionsIndex {
    const byId = useDefinitionsStore(s => s.byId);
    const builtIn = useDefinitionsStore(s => s.builtIn);
    const {workspaceId, target, includeBuiltIn} = options;

    return useMemo(
        () => build({byId, builtIn}, {workspaceId, target, includeBuiltIn}),
        [build, byId, builtIn, workspaceId, target, includeBuiltIn]
    );
}

/**
 * Index of the known definitions by search slug (`@createdAt`, `title_text_s`...)
 * — used by AQL; resolves the definitions of `searchSlugs`.
 */
export function useDefinitionsBySearchSlug(
    options: IndexOptions & {searchSlugs?: string[]} = {}
): DefinitionsIndex {
    const {searchSlugs} = options;
    const slugs = useMemo(
        () =>
            searchSlugs?.map(slugOfSearchSlug).filter((s): s is string => !!s),
        [searchSlugs]
    );
    useEnsureDefinitions({slugs}, options.includeBuiltIn ?? true);

    return useIndex(indexBySearchSlug, options);
}

export function useDefinitionBySearchSlug(
    name: string | undefined
): AttributeDefinitionOrBuiltIn | undefined {
    const searchSlugs = useMemo(() => (name ? [name] : []), [name]);
    const index = useDefinitionsBySearchSlug({searchSlugs});

    return name
        ? (index[name] ?? index[name.replace(/_[a-z]{2}$/, '')])
        : undefined;
}

/**
 * Key of a definition in AQL queries: the API resolves attribute fields by
 * their `slug` (the `searchSlug` — `slug_type_m` — is the sort / facet key),
 * built-in attributes keep their `@name`.
 */
export function aqlKey(d: AttributeDefinitionOrBuiltIn): string {
    return (d as BuiltInAttribute).builtIn ? d.searchSlug : d.slug;
}

/**
 * Index of the known definitions by AQL field name (see `aqlKey`): used to
 * validate and build conditions; resolves the definitions of `slugs`.
 */
export function useDefinitionsBySlug(
    options: IndexOptions & {slugs?: string[]} = {}
): DefinitionsIndex {
    useEnsureDefinitions(
        {slugs: options.slugs},
        options.includeBuiltIn ?? true
    );

    return useIndex(indexBySlug, options);
}

export function useDefinitionBySlug(
    slug: string | undefined,
    options: IndexOptions = {}
): AttributeDefinitionOrBuiltIn | undefined {
    const slugs = useMemo(() => (slug ? [slug] : []), [slug]);
    const index = useDefinitionsBySlug({...options, slugs});

    return slug ? index[slug] : undefined;
}

/**
 * Index of the known definitions by id (and by built-in slug for built-ins);
 * resolves the definitions of `ids`.
 */
export function useDefinitionsById(
    options: IndexOptions & {ids?: string[]} = {}
): DefinitionsIndex {
    useEnsureDefinitions({ids: options.ids}, options.includeBuiltIn ?? true);

    return useIndex(indexById, options);
}

/** Every known definition having this slug, one per workspace */
export function useDefinitionsWithSlug(
    slug: string | undefined
): AttributeDefinition[] {
    const slugs = useMemo(() => (slug ? [slug] : []), [slug]);
    useEnsureDefinitions({slugs});
    const byId = useDefinitionsStore(s => s.byId);

    return useMemo(
        () => (slug ? Object.values(byId).filter(d => d.slug === slug) : []),
        [byId, slug]
    );
}

type BuiltInResolver = (asset: import('@/types/api').Asset) => unknown;

export const builtInValueResolvers: Partial<
    Record<BuiltInEnum, BuiltInResolver>
> = {
    [BuiltInEnum.Checksum]: a => a.source?.checksum,
    [BuiltInEnum.Collection]: a => a.collections,
    [BuiltInEnum.CreatedAt]: a => a.createdAt,
    [BuiltInEnum.DocUniqueId]: a => a.source?.docUniqueId,
    [BuiltInEnum.EditedAt]: a => a.editedAt,
    [BuiltInEnum.FileExtension]: a => a.source?.extension,
    [BuiltInEnum.FileFamily]: a => getFileFamily(a.source?.type),
    [BuiltInEnum.FileName]: a => a.source?.fileName,
    [BuiltInEnum.HasSource]: a => !!a.source,
    [BuiltInEnum.FileSize]: a => a.source?.size,
    [BuiltInEnum.FileType]: a => a.source?.type,
    [BuiltInEnum.Id]: a => a.id,
    [BuiltInEnum.Owner]: a => a.owner,
    [BuiltInEnum.Privacy]: a => a.privacy,
    [BuiltInEnum.Tag]: a => a.tags,
    [BuiltInEnum.IsStory]: a => !!a.storyCollection,
    [BuiltInEnum.Story]: a => a.storyCollection,
    [BuiltInEnum.Workspace]: a => a.workspace,
    [BuiltInEnum.Deleted]: a => !!a.deleted,
    [BuiltInEnum.AssetStatus]: a => a.status,
};

export const builtInTypes: Partial<Record<BuiltInEnum, AttributeType>> = {
    [BuiltInEnum.CreatedAt]: AttributeType.DateTime,
    [BuiltInEnum.EditedAt]: AttributeType.DateTime,
    [BuiltInEnum.FileSize]: AttributeType.FileSize,
    [BuiltInEnum.Privacy]: AttributeType.Privacy,
    [BuiltInEnum.Tag]: AttributeType.Tag,
    [BuiltInEnum.Collection]: AttributeType.CollectionPath,
    [BuiltInEnum.Workspace]: AttributeType.Workspace,
    [BuiltInEnum.Owner]: AttributeType.User,
    [BuiltInEnum.Rendition]: AttributeType.Rendition,
    [BuiltInEnum.AssetStatus]: AttributeType.AssetStatus,
    [BuiltInEnum.Story]: AttributeType.Story,
    [BuiltInEnum.Similar]: AttributeType.Asset,
    [BuiltInEnum.FileFamily]: AttributeType.FileFamily,
};
