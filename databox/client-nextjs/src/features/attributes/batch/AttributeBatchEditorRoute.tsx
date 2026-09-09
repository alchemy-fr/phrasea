'use client';

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    CheckIcon,
    LockIcon,
    MinusIcon,
    PlusIcon,
    RedoIcon,
    SaveIcon,
    UndoIcon,
    XIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, Attribute, AttributeDefinition} from '@/types/api';
import {AttributeBatchActionEnum, AttributeType, EntityName} from '@/types/api';
import {
    attributeBatchUpdate,
    getAssetAttributes,
    isAssetEligibleForDefinition,
    searchAssets,
} from '@/lib/api/assets';
import {
    useDefinitionsStore,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {useCloseRoute} from '@/components/modals/RouteDialog';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {FullPageLoader} from '@/components/ui/loader';
import {
    Badge,
    EmptyState,
    Tabs,
    TabsList,
    TabsTrigger,
} from '@/components/ui/misc';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {AttributeWidget} from '@/features/attributes/widgets/AttributeWidget';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useSelectAllKey} from '@/hooks/useSelectAllKey';
import {NO_LOCALE} from '@/lib/utils/locale';
import {getAttributeType} from '@/features/attributes/types/registry';
import {useFormatContext} from '@/features/attributes/AttributeValue';
import {cn} from '@/lib/utils/cn';
import {debounce, deepEquals} from '@/lib/utils/misc';
import {Flag} from '@/components/ui/flag';
import {Input} from '@/components/ui/input';

/** per asset, per definition, per locale => values */
type ValueMap = Record<string, Record<string, Record<string, unknown[]>>>;

type EditorState = {
    values: ValueMap;
    subSelection: string[];
    currentDefinition: string | undefined;
};

const TAGS_DEFINITION_ID = '__tags';

function keyOf(v: unknown): string {
    return typeof v === 'object' ? JSON.stringify(v) : String(v);
}

/**
 * Bulk attribute editor: applies the same edits to a sub-selection of assets
 * with per-value coverage, indeterminate states, undo / redo and a diff
 * preview before saving.
 */
export function AttributeBatchEditorRoute() {
    const {t} = useTranslation();
    const close = useCloseRoute();
    const {openModal} = useModals();
    const ids = useMemo<string[]>(() => {
        try {
            return JSON.parse(sessionStorage.getItem('dbx.batch-edit') ?? '[]');
        } catch {
            return [];
        }
    }, []);

    const assetsQuery = useQuery({
        queryKey: ['batch-edit', 'assets', ids],
        queryFn: () => searchAssets({ids, limit: ids.length || 1}),
        enabled: ids.length > 0,
    });
    const attributesQuery = useQuery({
        queryKey: ['batch-edit', 'attributes', ids],
        queryFn: () => getAssetAttributes(ids),
        enabled: ids.length > 0,
    });
    const assets = useMemo(
        () => assetsQuery.data?.items ?? [],
        [assetsQuery.data?.items]
    );
    const workspaceId = assets[0]?.workspace.id;
    const loadWorkspace = useDefinitionsStore(s => s.loadWorkspace);
    const allDefinitions = useDefinitionsStore(s => s.definitions);
    useEffect(() => {
        if (workspaceId) {
            void loadWorkspace(workspaceId);
        }
    }, [workspaceId, loadWorkspace]);

    const definitions = useMemo<AttributeDefinition[]>(() => {
        const defs = allDefinitions.filter(
            d =>
                workspaceId &&
                workspaceIdOf(d) === workspaceId &&
                d.editable &&
                d.editableInGui
        );
        const tagsDef = {
            id: TAGS_DEFINITION_ID,
            name: 'tags',
            displayName: t('common.tags', 'Tags'),
            type: AttributeType.Tag,
            multiple: true,
            editable: true,
            editableInGui: true,
            canEdit: true,
            translatable: false,
            target: 0,
            workspace: workspaceId ?? '',
        } as unknown as AttributeDefinition;

        return [tagsDef, ...defs];
    }, [allDefinitions, workspaceId, t]);

    const remote = useMemo<ValueMap>(() => {
        const map: ValueMap = {};
        assets.forEach(a => {
            map[a.id] = {
                [TAGS_DEFINITION_ID]: {
                    [NO_LOCALE]: (a.tags ?? []).map(tg => tg.id),
                },
            };
        });
        (attributesQuery.data ?? []).forEach(
            (attr: Attribute & {asset?: {id: string}}) => {
                const assetId = attr.asset?.id ?? (attr as any).assetId;
                if (!assetId || !map[assetId]) {
                    return;
                }
                const defId = attr.definition.id;
                const locale = attr.locale ?? NO_LOCALE;
                const typeDef = getAttributeType(attr.definition.type);
                const value = typeDef.normalize
                    ? typeDef.normalize(attr.value)
                    : attr.value;
                map[assetId][defId] ??= {};
                map[assetId][defId][locale] = [
                    ...(map[assetId][defId][locale] ?? []),
                    value,
                ];
            }
        );

        return map;
    }, [assets, attributesQuery.data]);

    const [history, setHistory] = useState<{
        past: EditorState[];
        present: EditorState;
        future: EditorState[];
    }>({
        past: [],
        present: {values: {}, subSelection: [], currentDefinition: undefined},
        future: [],
    });
    const [initialized, setInitialized] = useState(false);
    useEffect(() => {
        if (!initialized && assets.length > 0 && attributesQuery.isSuccess) {
            setHistory({
                past: [],
                present: {
                    values: structuredClone(remote),
                    subSelection: assets.map(a => a.id),
                    currentDefinition: definitions[1]?.id ?? definitions[0]?.id,
                },
                future: [],
            });
            setInitialized(true);
        }
    }, [initialized, assets, attributesQuery.isSuccess, remote, definitions]);

    const state = history.present;
    const commit = useCallback((next: Partial<EditorState>) => {
        setHistory(h => ({
            past: [...h.past.slice(-49), h.present],
            present: {...h.present, ...next},
            future: [],
        }));
    }, []);
    const undo = () =>
        setHistory(h =>
            h.past.length
                ? {
                      past: h.past.slice(0, -1),
                      present: h.past[h.past.length - 1],
                      future: [h.present, ...h.future],
                  }
                : h
        );
    const redo = () =>
        setHistory(h =>
            h.future.length
                ? {
                      past: [...h.past, h.present],
                      present: h.future[0],
                      future: h.future.slice(1),
                  }
                : h
        );

    const [locale, setLocale] = useState<string>(NO_LOCALE);
    const [rightTab, setRightTab] = useState<'values' | 'preview'>('values');
    const [previewIndex, setPreviewIndex] = useState(0);

    useSelectAllKey(() => commit({subSelection: assets.map(a => a.id)}));

    const currentDef = definitions.find(d => d.id === state.currentDefinition);
    const eligible = useMemo(
        () =>
            currentDef
                ? state.subSelection.filter(
                      id =>
                          assets.find(a => a.id === id) &&
                          (currentDef.id === TAGS_DEFINITION_ID ||
                              isAssetEligibleForDefinition(
                                  assets.find(a => a.id === id)!,
                                  currentDef
                              ))
                  )
                : [],
        [currentDef, state.subSelection, assets]
    );
    const locales = useMemo(
        () => [
            ...new Set(
                definitions.flatMap(d =>
                    d.translatable ? (d.locales ?? []) : []
                )
            ),
        ],
        [definitions]
    );
    const effectiveLocale = currentDef?.translatable ? locale : NO_LOCALE;

    const valuesFor = (
        assetId: string,
        defId: string,
        loc: string
    ): unknown[] => state.values[assetId]?.[defId]?.[loc] ?? [];

    /** distinct values across eligible assets with coverage */
    const distinct = useMemo(() => {
        if (!currentDef) {
            return [];
        }
        const counts = new Map<string, {value: unknown; count: number}>();
        eligible.forEach(id => {
            valuesFor(id, currentDef.id, effectiveLocale).forEach(v => {
                const k = keyOf(v);
                counts.set(k, {
                    value: v,
                    count: (counts.get(k)?.count ?? 0) + 1,
                });
            });
        });

        return [...counts.entries()]
            .map(([k, v]) => ({key: k, ...v}))
            .sort((a, b) => b.count - a.count);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentDef, eligible, effectiveLocale, state.values]);

    const setSingle = (value: unknown) => {
        if (!currentDef) {
            return;
        }
        const values = structuredClone(state.values);
        eligible.forEach(id => {
            values[id] ??= {};
            values[id][currentDef.id] ??= {};
            values[id][currentDef.id][effectiveLocale] =
                value === undefined || value === '' ? [] : [value];
        });
        commit({values});
    };
    const setSingleRef = useRef(setSingle);
    useEffect(() => {
        setSingleRef.current = setSingle;
    });
    const debouncedSetSingle = useMemo(
        () => debounce((v: unknown) => setSingleRef.current(v), 500),
        []
    );

    const addToAll = (value: unknown) => {
        if (!currentDef || value === undefined || value === '') {
            return;
        }
        const values = structuredClone(state.values);
        eligible.forEach(id => {
            values[id] ??= {};
            values[id][currentDef.id] ??= {};
            const list = values[id][currentDef.id][effectiveLocale] ?? [];
            if (!list.some(v => keyOf(v) === keyOf(value))) {
                values[id][currentDef.id][effectiveLocale] = [...list, value];
            }
        });
        commit({values});
    };
    const removeFromAll = (value: unknown) => {
        if (!currentDef) {
            return;
        }
        const values = structuredClone(state.values);
        eligible.forEach(id => {
            const list = values[id]?.[currentDef.id]?.[effectiveLocale];
            if (list) {
                values[id][currentDef.id][effectiveLocale] = list.filter(
                    v => keyOf(v) !== keyOf(value)
                );
            }
        });
        commit({values});
    };
    const toggleForAsset = (assetId: string, value: unknown) => {
        if (!currentDef) {
            return;
        }
        const values = structuredClone(state.values);
        values[assetId] ??= {};
        values[assetId][currentDef.id] ??= {};
        const list = values[assetId][currentDef.id][effectiveLocale] ?? [];
        const has = list.some(v => keyOf(v) === keyOf(value));
        values[assetId][currentDef.id][effectiveLocale] = has
            ? list.filter(v => keyOf(v) !== keyOf(value))
            : currentDef.multiple
              ? [...list, value]
              : [value];
        commit({values});
    };

    const dirty = !deepEquals(state.values, remote);

    /** Diff => batch actions per asset */
    const computeActions = () => {
        const actions: {
            definition: AttributeDefinition;
            locale: string;
            action: AttributeBatchActionEnum;
            value: unknown;
            assets: string[];
        }[] = [];
        definitions.forEach(def => {
            const locs = new Set<string>();
            assets.forEach(a => {
                Object.keys(state.values[a.id]?.[def.id] ?? {}).forEach(l =>
                    locs.add(l)
                );
                Object.keys(remote[a.id]?.[def.id] ?? {}).forEach(l =>
                    locs.add(l)
                );
            });
            locs.forEach(loc => {
                const byKey = new Map<
                    string,
                    {
                        action: AttributeBatchActionEnum;
                        value: unknown;
                        assets: string[];
                    }
                >();
                assets.forEach(a => {
                    const before = remote[a.id]?.[def.id]?.[loc] ?? [];
                    const after = state.values[a.id]?.[def.id]?.[loc] ?? [];
                    if (deepEquals(before, after)) {
                        return;
                    }
                    if (def.multiple) {
                        after
                            .filter(
                                v => !before.some(b => keyOf(b) === keyOf(v))
                            )
                            .forEach(v => {
                                const k = `add:${keyOf(v)}`;
                                const e = byKey.get(k) ?? {
                                    action: AttributeBatchActionEnum.Add,
                                    value: v,
                                    assets: [],
                                };
                                e.assets.push(a.id);
                                byKey.set(k, e);
                            });
                        before
                            .filter(
                                v => !after.some(b => keyOf(b) === keyOf(v))
                            )
                            .forEach(v => {
                                const k = `del:${keyOf(v)}`;
                                const e = byKey.get(k) ?? {
                                    action: AttributeBatchActionEnum.Delete,
                                    value: v,
                                    assets: [],
                                };
                                e.assets.push(a.id);
                                byKey.set(k, e);
                            });
                    } else if (after.length === 0) {
                        const k = 'del';
                        const e = byKey.get(k) ?? {
                            action: AttributeBatchActionEnum.Delete,
                            value: undefined,
                            assets: [],
                        };
                        e.assets.push(a.id);
                        byKey.set(k, e);
                    } else {
                        const k = `set:${keyOf(after[0])}`;
                        const e = byKey.get(k) ?? {
                            action: AttributeBatchActionEnum.Set,
                            value: after[0],
                            assets: [],
                        };
                        e.assets.push(a.id);
                        byKey.set(k, e);
                    }
                });
                byKey.forEach(e =>
                    actions.push({definition: def, locale: loc, ...e})
                );
            });
        });

        return actions;
    };

    const save = async () => {
        const actions = computeActions();
        openModal(SavePreviewDialog as any, {
            actions,
            assetCount: assets.length,
            onConfirm: async () => {
                const tagActions = actions.filter(
                    a => a.definition.id === TAGS_DEFINITION_ID
                );
                const attrActions = actions.filter(
                    a => a.definition.id !== TAGS_DEFINITION_ID
                );
                if (attrActions.length > 0) {
                    await attributeBatchUpdate(
                        assets.map(a => a.id),
                        attrActions.map(a => ({
                            action: a.action,
                            definitionId: a.definition.id,
                            value:
                                a.action === AttributeBatchActionEnum.Delete &&
                                a.definition.multiple
                                    ? a.value
                                    : a.action ===
                                        AttributeBatchActionEnum.Delete
                                      ? undefined
                                      : a.value,
                            locale:
                                a.locale !== NO_LOCALE ? a.locale : undefined,
                            assets: a.assets,
                        }))
                    );
                }
                if (tagActions.length > 0) {
                    const {patchAsset} = await import('@/lib/api/assets');
                    await Promise.all(
                        assets.map(a =>
                            tagActions.some(ta => ta.assets.includes(a.id))
                                ? patchAsset(a.id, {
                                      tags: (
                                          state.values[a.id]?.[
                                              TAGS_DEFINITION_ID
                                          ]?.[NO_LOCALE] ?? []
                                      ).map(id => `/${EntityName.Tag}/${id}`),
                                  })
                                : Promise.resolve()
                        )
                    );
                }
                toast.success(t('batch_edit.saved', 'Attributes updated'));
                await attributesQuery.refetch();
                await assetsQuery.refetch();
                setInitialized(false);
            },
        });
    };

    const requestClose = () => {
        if (dirty) {
            openModal(ConfirmDialog, {
                title: t('batch_edit.discard.title', 'Discard changes?'),
                destructive: true,
                confirmLabel: t('batch_edit.discard', 'Discard'),
                onConfirm: close,
            });
        } else {
            close();
        }
    };

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (
                e.key === 'Tab' &&
                !(
                    document.activeElement instanceof HTMLInputElement &&
                    document.activeElement.type === 'text' &&
                    document.activeElement.closest('[data-batch-values]')
                )
            ) {
                const list = definitions.filter(d => d.canEdit !== false);
                const i = list.findIndex(d => d.id === state.currentDefinition);
                if (i >= 0) {
                    e.preventDefault();
                    const next =
                        list[
                            (i + (e.shiftKey ? list.length - 1 : 1)) %
                                list.length
                        ];
                    commit({currentDefinition: next.id});
                }
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [definitions, state.currentDefinition, commit]);

    if (ids.length === 0) {
        return (
            <div className="fixed inset-0 z-50 bg-background">
                <EmptyState
                    className="h-full"
                    title={t('batch_edit.no_selection', 'No asset selected')}
                    action={
                        <Button onClick={close}>
                            {t('common.close', 'Close')}
                        </Button>
                    }
                />
            </div>
        );
    }
    if (!initialized || !currentDef) {
        return (
            <div className="fixed inset-0 z-50 bg-background">
                <FullPageLoader />
            </div>
        );
    }

    const previewAssets = assets.filter(a => state.subSelection.includes(a.id));
    const previewAsset =
        previewAssets[Math.min(previewIndex, previewAssets.length - 1)];

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-background">
            <header className="flex h-12 shrink-0 items-center gap-2 border-b px-3">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={requestClose}
                    aria-label={t('common.close', 'Close')}
                >
                    <XIcon />
                </Button>
                <h1 className="flex-1 text-sm font-semibold">
                    {t(
                        'batch_edit.title',
                        'Edit attributes of {{count}} assets',
                        {count: assets.length}
                    )}
                </h1>
                <Tooltip content={t('common.undo', 'Undo')}>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        disabled={history.past.length === 0}
                        onClick={undo}
                    >
                        <UndoIcon />
                    </Button>
                </Tooltip>
                <Tooltip content={t('common.redo', 'Redo')}>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        disabled={history.future.length === 0}
                        onClick={redo}
                    >
                        <RedoIcon />
                    </Button>
                </Tooltip>
                <Button size="sm" disabled={!dirty} onClick={save}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </header>

            {/* thumbnails band */}
            <div className="flex h-28 shrink-0 items-center gap-2 overflow-x-auto border-b px-3">
                <span className="mr-1 text-xs text-muted-foreground whitespace-nowrap">
                    {t(
                        'batch_edit.sub_selection',
                        '{{count}} / {{total}} selected',
                        {count: state.subSelection.length, total: assets.length}
                    )}
                </span>
                {assets.map(a => {
                    const selected = state.subSelection.includes(a.id);
                    const notEligible =
                        currentDef.id !== TAGS_DEFINITION_ID &&
                        !isAssetEligibleForDefinition(a, currentDef);
                    const assetValues = valuesFor(
                        a.id,
                        currentDef.id,
                        effectiveLocale
                    );

                    return (
                        <div
                            key={a.id}
                            className={cn(
                                'group/thumb relative size-20 shrink-0 overflow-hidden rounded-md border bg-media-bg',
                                selected ? 'ring-2 ring-primary' : 'opacity-50',
                                notEligible && 'opacity-25'
                            )}
                        >
                            <button
                                type="button"
                                className="size-full"
                                onClick={e => {
                                    if (e.ctrlKey || e.metaKey) {
                                        commit({
                                            subSelection: selected
                                                ? state.subSelection.filter(
                                                      id => id !== a.id
                                                  )
                                                : [...state.subSelection, a.id],
                                        });
                                    } else {
                                        commit({subSelection: [a.id]});
                                    }
                                }}
                                title={a.name}
                            >
                                <AssetThumb asset={a} size={80} />
                            </button>
                            {assetValues.length > 0 ? (
                                <span className="pointer-events-none absolute right-1 bottom-1 rounded bg-background/90 px-1 text-[10px] font-semibold">
                                    {assetValues.length}
                                </span>
                            ) : null}
                        </div>
                    );
                })}
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        commit({subSelection: assets.map(a => a.id)})
                    }
                >
                    {t('list.select_all', 'Select all')}
                </Button>
            </div>

            <div className="grid min-h-0 flex-1 grid-cols-[16rem_1fr_20rem]">
                {/* definitions */}
                <aside className="overflow-y-auto border-r">
                    {definitions.map(def => {
                        const vals = new Set<string>();
                        let indeterminate = false;
                        let first: string | undefined;
                        state.subSelection.forEach((id, i) => {
                            const k = keyOf(
                                valuesFor(
                                    id,
                                    def.id,
                                    def.translatable ? locale : NO_LOCALE
                                )
                                    .map(keyOf)
                                    .sort()
                            );
                            if (i === 0) first = k;
                            if (k !== first) indeterminate = true;
                            valuesFor(
                                id,
                                def.id,
                                def.translatable ? locale : NO_LOCALE
                            ).forEach(v => vals.add(keyOf(v)));
                        });
                        const changed = state.subSelection.some(
                            id =>
                                !deepEquals(
                                    state.values[id]?.[def.id],
                                    remote[id]?.[def.id]
                                )
                        );

                        return (
                            <button
                                key={def.id}
                                type="button"
                                className={cn(
                                    'flex w-full flex-col gap-0.5 border-b px-3 py-2 text-left text-sm hover:bg-accent/50',
                                    state.currentDefinition === def.id &&
                                        'bg-primary/10'
                                )}
                                onClick={() =>
                                    commit({currentDefinition: def.id})
                                }
                            >
                                <span className="flex items-center gap-1.5">
                                    <span className="min-w-0 flex-1 truncate font-medium">
                                        {def.displayName ?? def.name}
                                    </span>
                                    {!def.canEdit ? (
                                        <LockIcon className="size-3 text-muted-foreground" />
                                    ) : null}
                                    {changed ? (
                                        <span className="size-2 rounded-full bg-primary" />
                                    ) : null}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {indeterminate ? (
                                        <span className="text-warning-foreground italic">
                                            {t(
                                                'batch_edit.indeterminate',
                                                'Indeterminate'
                                            )}
                                        </span>
                                    ) : vals.size === 0 ? (
                                        '—'
                                    ) : def.multiple ? (
                                        t(
                                            'batch_edit.values_count',
                                            '{{count}} value(s)',
                                            {count: vals.size}
                                        )
                                    ) : (
                                        [...vals][0]
                                    )}
                                </span>
                            </button>
                        );
                    })}
                </aside>

                {/* editor */}
                <section className="overflow-y-auto p-4" data-batch-values>
                    <div className="mb-3 flex items-center gap-2">
                        <h2 className="text-base font-semibold">
                            {currentDef.displayName ?? currentDef.name}
                        </h2>
                        <Badge variant="muted">{currentDef.type}</Badge>
                        {currentDef.translatable && locales.length > 0 ? (
                            <Tabs
                                value={locale}
                                onValueChange={setLocale}
                                className="ml-auto"
                            >
                                <TabsList className="h-8">
                                    {locales.map(l => (
                                        <TabsTrigger
                                            key={l}
                                            value={l}
                                            className="h-6 px-2 text-xs"
                                        >
                                            <Flag locale={l} /> {l}
                                        </TabsTrigger>
                                    ))}
                                    <TabsTrigger
                                        value={NO_LOCALE}
                                        className="h-6 px-2 text-xs"
                                    >
                                        {t(
                                            'attribute.untranslated',
                                            'Untranslated'
                                        )}
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        ) : null}
                    </div>
                    {eligible.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'batch_edit.no_eligible',
                                'None of the selected assets accepts this attribute.'
                            )}
                        </p>
                    ) : currentDef.multiple ? (
                        <MultiValueEditor
                            definition={currentDef}
                            distinct={distinct}
                            total={eligible.length}
                            onAdd={addToAll}
                            onRemove={removeFromAll}
                            workspaceId={workspaceId}
                        />
                    ) : (
                        <SingleValueEditor
                            definition={currentDef}
                            distinct={distinct}
                            total={eligible.length}
                            onChange={debouncedSetSingle}
                            onCommit={setSingle}
                            workspaceId={workspaceId}
                        />
                    )}
                </section>

                {/* suggestions / preview */}
                <aside className="flex min-h-0 flex-col border-l">
                    <Tabs
                        value={rightTab}
                        onValueChange={v =>
                            setRightTab(v as 'values' | 'preview')
                        }
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <TabsList className="m-2 grid grid-cols-2">
                            <TabsTrigger value="values">
                                {t('batch_edit.tab_values', 'Values')}
                            </TabsTrigger>
                            <TabsTrigger value="preview">
                                {t('batch_edit.tab_preview', 'Preview')}
                            </TabsTrigger>
                        </TabsList>
                        {rightTab === 'values' ? (
                            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                                <ValuesSuggestions
                                    definition={currentDef}
                                    distinct={distinct}
                                    total={eligible.length}
                                    remote={eligible.flatMap(
                                        id =>
                                            remote[id]?.[currentDef.id]?.[
                                                effectiveLocale
                                            ] ?? []
                                    )}
                                    onApply={v =>
                                        currentDef.multiple
                                            ? addToAll(v)
                                            : setSingle(v)
                                    }
                                    onSelectAssets={v =>
                                        commit({
                                            subSelection: assets
                                                .filter(a =>
                                                    valuesFor(
                                                        a.id,
                                                        currentDef.id,
                                                        effectiveLocale
                                                    ).some(
                                                        x =>
                                                            keyOf(x) ===
                                                            keyOf(v)
                                                    )
                                                )
                                                .map(a => a.id),
                                        })
                                    }
                                />
                            </div>
                        ) : (
                            <div className="flex min-h-0 flex-1 flex-col p-2">
                                {previewAsset ? (
                                    <>
                                        <div className="flex min-h-0 flex-1 items-center justify-center rounded bg-media-bg">
                                            {(previewAsset.preview?.file ??
                                            previewAsset.thumbnail?.file) ? (
                                                <FilePlayer
                                                    file={
                                                        (previewAsset.preview
                                                            ?.file ??
                                                            previewAsset
                                                                .thumbnail!
                                                                .file)!
                                                    }
                                                    controls={false}
                                                />
                                            ) : (
                                                <AssetThumb
                                                    asset={previewAsset}
                                                />
                                            )}
                                        </div>
                                        <div className="mt-2 flex items-center justify-between text-xs">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={previewIndex <= 0}
                                                onClick={() =>
                                                    setPreviewIndex(i => i - 1)
                                                }
                                            >
                                                ←
                                            </Button>
                                            <span className="truncate">
                                                {previewAsset.name}
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={
                                                    previewIndex >=
                                                    previewAssets.length - 1
                                                }
                                                onClick={() =>
                                                    setPreviewIndex(i => i + 1)
                                                }
                                            >
                                                →
                                            </Button>
                                        </div>
                                        {currentDef.multiple ? (
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {distinct.map(d => {
                                                    const has = valuesFor(
                                                        previewAsset.id,
                                                        currentDef.id,
                                                        effectiveLocale
                                                    ).some(
                                                        x => keyOf(x) === d.key
                                                    );

                                                    return (
                                                        <Button
                                                            key={d.key}
                                                            variant={
                                                                has
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                            size="sm"
                                                            className="h-7 text-xs"
                                                            onClick={() =>
                                                                toggleForAsset(
                                                                    previewAsset.id,
                                                                    d.value
                                                                )
                                                            }
                                                        >
                                                            {has ? (
                                                                <MinusIcon />
                                                            ) : (
                                                                <PlusIcon />
                                                            )}{' '}
                                                            {String(d.value)}
                                                        </Button>
                                                    );
                                                })}
                                            </div>
                                        ) : null}
                                    </>
                                ) : null}
                            </div>
                        )}
                    </Tabs>
                </aside>
            </div>
        </div>
    );
}

type Distinct = {key: string; value: unknown; count: number}[];

function SingleValueEditor({
    definition,
    distinct,
    total,
    onChange,
    onCommit,
    workspaceId,
}: {
    definition: AttributeDefinition;
    distinct: Distinct;
    total: number;
    onChange: (v: unknown) => void;
    onCommit: (v: unknown) => void;
    workspaceId?: string;
}) {
    const {t} = useTranslation();
    const indeterminate =
        distinct.length > 1 ||
        (distinct.length === 1 && distinct[0].count < total);
    const [local, setLocal] = useState<unknown>(
        indeterminate ? '' : (distinct[0]?.value ?? '')
    );
    useEffect(() => {
        setLocal(indeterminate ? '' : (distinct[0]?.value ?? ''));
    }, [definition.id, indeterminate, distinct]);

    return (
        <div className="space-y-2">
            <AttributeWidget
                id={`batch-${definition.id}`}
                definition={definition}
                value={local}
                indeterminate={
                    indeterminate && (local === '' || local === undefined)
                }
                disabled={!definition.canEdit}
                workspaceId={workspaceId}
                onChange={v => {
                    setLocal(v);
                    if (
                        definition.type === AttributeType.Text ||
                        definition.type === AttributeType.Textarea ||
                        definition.type === AttributeType.Number
                    ) {
                        onChange(v);
                    } else {
                        onCommit(v);
                    }
                }}
            />
            {indeterminate ? (
                <p className="text-xs text-muted-foreground">
                    {t(
                        'batch_edit.indeterminate_help',
                        'Selected assets have different values. Typing a value applies it to all of them.'
                    )}
                </p>
            ) : null}
        </div>
    );
}

function MultiValueEditor({
    definition,
    distinct,
    total,
    onAdd,
    onRemove,
    workspaceId,
}: {
    definition: AttributeDefinition;
    distinct: Distinct;
    total: number;
    onAdd: (v: unknown) => void;
    onRemove: (v: unknown) => void;
    workspaceId?: string;
}) {
    const {t} = useTranslation();
    const ctx = useFormatContext();
    const [draft, setDraft] = useState<unknown>('');
    const typeDef = getAttributeType(definition.type);

    return (
        <div className="space-y-3">
            <ul className="space-y-1">
                {distinct.map(d => (
                    <li
                        key={d.key}
                        className="flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
                    >
                        <span className="min-w-0 flex-1 truncate">
                            {typeDef.formatString(d.value, undefined, ctx) ||
                                String(d.value)}
                        </span>
                        <PartPercentage part={d.count} total={total} />
                        {d.count < total ? (
                            <Tooltip
                                content={t(
                                    'batch_edit.add_to_all',
                                    'Add to all selected assets'
                                )}
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={() => onAdd(d.value)}
                                >
                                    <PlusIcon />
                                </Button>
                            </Tooltip>
                        ) : null}
                        <Tooltip
                            content={t(
                                'batch_edit.remove_from_all',
                                'Remove from all selected assets'
                            )}
                        >
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                className="text-destructive"
                                onClick={() => onRemove(d.value)}
                            >
                                <MinusIcon />
                            </Button>
                        </Tooltip>
                    </li>
                ))}
            </ul>
            <div className="flex items-start gap-2">
                <div className="flex-1">
                    <AttributeWidget
                        id={`batch-add-${definition.id}`}
                        definition={definition}
                        value={draft}
                        onChange={v => {
                            if (
                                definition.type === AttributeType.Tag ||
                                definition.type === AttributeType.Entity ||
                                definition.type === AttributeType.User
                            ) {
                                onAdd(v);
                                setDraft('');
                            } else {
                                setDraft(v);
                            }
                        }}
                        onKeyDown={e => {
                            if (
                                e.key === 'Enter' &&
                                draft !== '' &&
                                draft !== undefined
                            ) {
                                e.preventDefault();
                                onAdd(draft);
                                setDraft('');
                            }
                        }}
                        workspaceId={workspaceId}
                    />
                </div>
                <Button
                    variant="outline"
                    disabled={draft === '' || draft === undefined}
                    onClick={() => {
                        onAdd(draft);
                        setDraft('');
                    }}
                >
                    <PlusIcon /> {t('batch_edit.add_value', 'Add to all')}
                </Button>
            </div>
        </div>
    );
}

function PartPercentage({part, total}: {part: number; total: number}) {
    const pct = total > 0 ? Math.round((part / total) * 100) : 0;

    return (
        <span className="flex w-24 items-center gap-1 text-[11px] text-muted-foreground tabular-nums">
            <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                <span
                    className="block h-full bg-primary"
                    style={{width: `${pct}%`}}
                />
            </span>
            {pct}%
        </span>
    );
}

function ValuesSuggestions({
    definition,
    distinct,
    total,
    remote,
    onApply,
    onSelectAssets,
}: {
    definition: AttributeDefinition;
    distinct: Distinct;
    total: number;
    remote: unknown[];
    onApply: (v: unknown) => void;
    onSelectAssets: (v: unknown) => void;
}) {
    const {t} = useTranslation();
    const ctx = useFormatContext();
    const typeDef = getAttributeType(definition.type);
    const [filter, setFilter] = useState('');
    const remoteKeys = new Set(remote.map(keyOf));

    return (
        <div className="space-y-2">
            <Input
                value={filter}
                onChange={e => setFilter(e.target.value)}
                placeholder={t('common.filter', 'Filter…')}
                className="h-8"
            />
            {distinct.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {t('batch_edit.no_values', 'No value yet')}
                </p>
            ) : null}
            <ul className="space-y-1">
                {distinct
                    .filter(
                        d =>
                            !filter ||
                            String(d.value)
                                .toLowerCase()
                                .includes(filter.toLowerCase())
                    )
                    .map(d => (
                        <li
                            key={d.key}
                            className={cn(
                                'group/sugg rounded-md border px-2 py-1.5 text-sm',
                                !remoteKeys.has(d.key) &&
                                    'border-primary/40 bg-primary/5'
                            )}
                        >
                            <div className="flex items-center gap-2">
                                <span className="min-w-0 flex-1 truncate">
                                    {typeDef.formatString(
                                        d.value,
                                        undefined,
                                        ctx
                                    ) || String(d.value)}
                                </span>
                                {!remoteKeys.has(d.key) ? (
                                    <Badge variant="secondary">
                                        {t('batch_edit.new', 'new')}
                                    </Badge>
                                ) : null}
                            </div>
                            <PartPercentage part={d.count} total={total} />
                            <div className="mt-1 flex gap-1 opacity-0 group-hover/sugg:opacity-100">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 text-xs"
                                    onClick={() => onApply(d.value)}
                                >
                                    <CheckIcon />{' '}
                                    {t(
                                        'batch_edit.apply_to_selected',
                                        'Apply to selected'
                                    )}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 text-xs"
                                    onClick={() => onSelectAssets(d.value)}
                                >
                                    {t(
                                        'batch_edit.select_assets',
                                        'Select assets'
                                    )}
                                </Button>
                            </div>
                        </li>
                    ))}
            </ul>
        </div>
    );
}

function SavePreviewDialog({
    open,
    onOpenChange,
    actions,
    assetCount,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    actions: ActionRow[];
    assetCount: number;
    onConfirm: () => Promise<void>;
}) {
    const {t} = useTranslation();
    const ctx = useFormatContext();

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            modalId="batch-save"
            title={t('batch_edit.confirm.title', 'Confirm changes?')}
            description={t(
                'batch_edit.confirm.help',
                '{{count}} change(s) on {{assets}} asset(s)',
                {count: actions.length, assets: assetCount}
            )}
            confirmLabel={t('common.save', 'Save')}
            onConfirm={onConfirm}
        >
            <ul className="max-h-72 space-y-1 overflow-y-auto text-sm">
                {actions.map((a, i) => {
                    const typeDef = getAttributeType(a.definition.type);

                    return (
                        <li
                            key={i}
                            className="flex items-center gap-2 rounded border px-2 py-1"
                        >
                            <Badge
                                variant={
                                    a.action === AttributeBatchActionEnum.Delete
                                        ? 'destructive'
                                        : a.action ===
                                            AttributeBatchActionEnum.Add
                                          ? 'success'
                                          : 'secondary'
                                }
                            >
                                {a.action}
                            </Badge>
                            <span className="font-medium">
                                {a.definition.displayName ?? a.definition.name}
                            </span>
                            {a.locale !== NO_LOCALE ? (
                                <Flag locale={a.locale} />
                            ) : null}
                            <span className="min-w-0 flex-1 truncate text-muted-foreground">
                                {a.value === undefined
                                    ? '—'
                                    : typeDef.formatString(
                                          a.value,
                                          undefined,
                                          ctx
                                      ) || String(a.value)}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t(
                                    'batch_edit.confirm.assets',
                                    '{{count}} asset(s)',
                                    {count: a.assets.length}
                                )}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </ConfirmDialog>
    );
}

type ActionRow = {
    definition: AttributeDefinition;
    locale: string;
    action: AttributeBatchActionEnum;
    value: unknown;
    assets: string[];
};

export type {Asset};
