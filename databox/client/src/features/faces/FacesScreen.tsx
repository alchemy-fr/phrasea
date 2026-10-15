'use client';

import {useCallback, useEffect, useMemo, useState} from 'react';
import {useSearchParams} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {
    useInfiniteQuery,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {toast} from 'sonner';
import {
    GitMergeIcon,
    PencilIcon,
    RefreshCwIcon,
    ScanFaceIcon,
    UserRoundIcon,
    UsersIcon,
} from 'lucide-react';
import type {Asset, Attribute, AttributeEntity} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    getAttributeEntities,
    getAttributesOfDefinition,
    mergeAttributeEntities,
    putAttributeEntity,
} from '@/lib/api/metadata';
import {
    deleteAssetAttribute,
    patchAssetAttribute,
    searchAssets,
} from '@/lib/api/assets';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {routes} from '@/lib/routes';
import {AttributeEntitySelect} from '@/components/form/selects';
import {EntityChip} from '@/components/chips';
import {Button} from '@/components/ui/button';
import {FilterInput} from '@/components/ui/filter-input';
import {Input} from '@/components/ui/input';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {SimpleSelect} from '@/components/ui/select';
import {usePageTrail} from '@/components/layout/layoutStore';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {cn} from '@/lib/utils/cn';
import {FaceCard, type FaceCardFace} from './FaceCard';
import {PersonGroup} from './PersonGroup';
import {
    assetIdOf,
    boxOf,
    type FaceRecognitionSetup,
    useFaceRecognitionSetups,
} from './useFaceRecognition';

const pageSize = 40;

/** Value of the person filter listing the persons nobody has named yet */
const unnamedPerson = '\0unnamed';

export const personsQueryKey = 'face-persons';
export const unknownPersonsQueryKey = 'face-unknown-persons';
export const facesQueryKey = 'faces';

type Filters = {
    workspace?: string;
    /** A person (entity ID), `unnamedPerson`, or undefined for every face */
    person?: string;
};

function filtersFromParams(params: URLSearchParams): Filters {
    return {
        workspace: params.get('workspace') ?? undefined,
        person: params.has('unnamed')
            ? unnamedPerson
            : (params.get('person') ?? undefined),
    };
}

function filtersToParams(filters: Filters): URLSearchParams {
    const params = new URLSearchParams();
    if (filters.workspace) {
        params.set('workspace', filters.workspace);
    }
    if (filters.person === unnamedPerson) {
        params.set('unnamed', '1');
    } else if (filters.person) {
        params.set('person', filters.person);
    }

    return params;
}

/**
 * Dedicated screen to name the faces detected by the face recognition: the
 * persons of the entity list on the left (with the look-alike groups nobody
 * named yet), the faces of the selection on the right, each one named in
 * place. Everything goes through the attributes and the entities of the
 * workspace, with their permissions.
 */
export function FacesScreen() {
    const {t} = useTranslation();
    usePageTrail(t('faces.title', 'Faces'), routes.faces());

    return (
        <RequireAuth>
            <FacesBrowser />
        </RequireAuth>
    );
}

function FacesBrowser() {
    const {t} = useTranslation();
    const searchParams = useSearchParams();
    const [filters, setFilters] = useState<Filters>(() =>
        filtersFromParams(searchParams)
    );
    const setups = useFaceRecognitionSetups();

    const workspaces = useCollectionStore(s => s.workspaces);
    const loadWorkspaces = useCollectionStore(s => s.loadWorkspaces);
    useEffect(() => {
        void loadWorkspaces();
    }, [loadWorkspaces]);

    // Shallow: the selection is local state, only the URL has to stay
    // shareable.
    const update = useCallback((patch: Partial<Filters>) => {
        setFilters(prev => {
            const next = {...prev, ...patch};
            const params = filtersToParams(next).toString();
            window.history.replaceState(
                window.history.state,
                '',
                `${routes.faces()}${params ? `?${params}` : ''}`
            );

            return next;
        });
    }, []);

    const setup = useMemo(
        () =>
            setups.data?.find(s => s.workspaceId === filters.workspace) ??
            setups.data?.[0],
        [setups.data, filters.workspace]
    );

    const workspaceOptions = useMemo(
        () =>
            (setups.data ?? []).map(s => ({
                value: s.workspaceId,
                label:
                    workspaces.find(w => w.id === s.workspaceId)?.displayName ??
                    s.workspaceId,
            })),
        [setups.data, workspaces]
    );

    if (setups.isLoading) {
        return (
            <div className="space-y-2 p-3">
                {[...Array(4)].map((_, i) => (
                    <Skeleton key={i} className="h-6" />
                ))}
            </div>
        );
    }

    if (!setup) {
        return (
            <EmptyState
                className="h-full"
                testId="faces-empty"
                icon={<ScanFaceIcon />}
                title={t('faces.empty', 'No face here')}
                description={t(
                    'faces.not_configured_help',
                    'Faces show up once a workspace you can use has the face recognition integration configured with an entity list.'
                )}
            />
        );
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <header className="flex shrink-0 flex-wrap items-center gap-2 border-b px-3 py-2">
                <ScanFaceIcon className="size-5 text-primary" />
                <h1 className="font-semibold">{t('faces.title', 'Faces')}</h1>
                <div className="flex-1" />
                {workspaceOptions.length > 1 ? (
                    <SimpleSelect
                        size="sm"
                        className="w-56"
                        value={setup.workspaceId}
                        onValueChange={value =>
                            update({workspace: value, person: undefined})
                        }
                        options={workspaceOptions}
                        id="faces-workspace"
                    />
                ) : null}
            </header>
            <WorkspaceFaces
                key={setup.workspaceId}
                setup={setup}
                person={filters.person}
                onSelectPerson={person => update({person})}
            />
        </div>
    );
}

function WorkspaceFaces({
    setup,
    person,
    onSelectPerson,
}: {
    setup: FaceRecognitionSetup;
    person: string | undefined;
    onSelectPerson: (person: string | undefined) => void;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [personQuery, setPersonQuery] = useState('');
    const listId = setup.entityList;
    const definition = setup.attributeDefinition;
    const grouped = person === unnamedPerson;

    const persons = useInfiniteQuery({
        queryKey: [personsQueryKey, listId],
        queryFn: ({pageParam}) =>
            getAttributeEntities({
                url: pageParam,
                list: listId,
                status: AttributeEntityStatus.Approved,
                usage: true,
                limit: 100,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const personItems = useMemo(
        () => persons.data?.pages.flatMap(p => p.items) ?? [],
        [persons.data]
    );
    const visiblePersons = useMemo(() => {
        const q = personQuery.trim().toLowerCase();

        return q
            ? personItems.filter(
                  p =>
                      p.value.toLowerCase().includes(q) ||
                      (p.note ?? '').toLowerCase().includes(q)
              )
            : personItems;
    }, [personItems, personQuery]);
    const selectedPerson = personItems.find(p => p.id === person);

    const unknowns = useInfiniteQuery({
        queryKey: [unknownPersonsQueryKey, listId],
        queryFn: ({pageParam}) =>
            getAttributeEntities({
                url: pageParam,
                list: listId,
                status: AttributeEntityStatus.Pending,
                usage: true,
                limit: pageSize,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const unknownItems = useMemo(
        () =>
            (unknowns.data?.pages.flatMap(p => p.items) ?? []).sort(
                (a, b) =>
                    (b.usage?.attributes ?? 0) - (a.usage?.attributes ?? 0)
            ),
        [unknowns.data]
    );
    const unknownCount = unknowns.data?.pages[0]?.total;

    // The faces: every face, those of a person, or those of the unknown persons loaded
    const faceValues = grouped
        ? unknownItems.map(p => p.id)
        : person
          ? [person]
          : undefined;
    const faces = useInfiniteQuery({
        queryKey: [facesQueryKey, definition, faceValues],
        queryFn: ({pageParam, signal}) =>
            getAttributesOfDefinition(
                {
                    url: pageParam,
                    definition: definition!,
                    value: faceValues,
                    limit: grouped ? 100 : pageSize,
                },
                signal
            ),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: !!definition && (!grouped || unknownItems.length > 0),
    });
    const faceItems = useMemo(
        () => faces.data?.pages.flatMap(p => p.items) ?? [],
        [faces.data]
    );
    const total = faces.data?.pages[0]?.total;

    // The assets of the faces, for their crops
    const assetIds = useMemo(
        () =>
            Array.from(
                new Set(
                    faceItems.map(assetIdOf).filter((id): id is string => !!id)
                )
            ),
        [faceItems]
    );
    const assets = useQuery({
        queryKey: ['faces-assets', assetIds],
        queryFn: ({signal}) =>
            searchAssets({ids: assetIds, limit: assetIds.length}, signal),
        enabled: assetIds.length > 0,
    });
    const assetsById = useMemo(
        () => new Map((assets.data?.items ?? []).map(a => [a.id, a])),
        [assets.data]
    );
    const cards = useMemo(
        () =>
            faceItems.flatMap((a: Attribute): FaceCardFace[] => {
                const asset = assetsById.get(assetIdOf(a) ?? '');
                const box = boxOf(a);

                return asset && box
                    ? [
                          {
                              id: a.id,
                              asset,
                              box,
                              origin: a.origin,
                              confidence: a.confidence,
                              entity: a.value ?? null,
                          },
                      ]
                    : [];
            }),
        [faceItems, assetsById]
    );

    const invalidateAll = () => {
        void queryClient.invalidateQueries({queryKey: [facesQueryKey]});
        void queryClient.invalidateQueries({queryKey: [personsQueryKey]});
        void queryClient.invalidateQueries({
            queryKey: [unknownPersonsQueryKey],
        });
    };

    const refresh = () => {
        void persons.refetch();
        void faces.refetch();
        if (grouped) {
            void unknowns.refetch();
        }
    };

    const fail = (e: any, fallback: string) => {
        toast.error(e?.message ?? fallback);
        throw e;
    };

    const onAssign = async (face: FaceCardFace, entityId: string) => {
        try {
            const updated = await patchAssetAttribute(face.id, {
                value: entityId,
            });
            invalidateAll();
            toast.success(
                t('faces.named_toast', {
                    defaultValue: 'Face named {{name}}',
                    name: updated.value?.value ?? '',
                })
            );
        } catch (e: any) {
            fail(e, t('faces.name_error', 'The face could not be named'));
        }
    };

    const onRemove = async (face: FaceCardFace) => {
        try {
            await deleteAssetAttribute(face.id);
            invalidateAll();
            toast.success(t('faces.forgotten', 'Face forgotten'));
        } catch (e: any) {
            fail(e, t('faces.name_error', 'The face could not be named'));
        }
    };

    const onNamed = async (named: AttributeEntity) => {
        invalidateAll();
        toast.success(
            t('faces.group_named_toast', {
                defaultValue: 'Faces named {{name}}',
                name: named.value,
            })
        );
    };

    const onMerge = async (source: AttributeEntity, targetId: string) => {
        try {
            const target = await mergeAttributeEntities(targetId, [source.id]);
            invalidateAll();
            toast.success(
                t('faces.group_named_toast', {
                    defaultValue: 'Faces named {{name}}',
                    name: target.value,
                })
            );
        } catch (e: any) {
            fail(e, t('faces.name_error', 'The face could not be named'));
        }
    };

    const canEdit = (face: FaceCardFace) =>
        face.asset.capabilities?.edit !== false;

    return (
        <div className="flex min-h-0 flex-1">
            <aside
                className="flex w-64 shrink-0 flex-col border-r"
                data-testid="faces-persons"
            >
                <div className="border-b p-2">
                    <FilterInput
                        value={personQuery}
                        onValueChange={setPersonQuery}
                        placeholder={t(
                            'faces.filter_persons',
                            'Filter persons…'
                        )}
                        className="text-sm"
                    />
                </div>
                <ul className="min-h-0 flex-1 overflow-y-auto py-1">
                    <PersonItem
                        icon={<UsersIcon />}
                        label={t('faces.all_faces', 'All faces')}
                        selected={!person}
                        onSelect={() => onSelectPerson(undefined)}
                        testId="faces-person-all"
                    />
                    <PersonItem
                        icon={<ScanFaceIcon />}
                        label={t('faces.unnamed', 'Unnamed')}
                        count={unknownCount}
                        selected={grouped}
                        onSelect={() => onSelectPerson(unnamedPerson)}
                        testId="faces-person-unnamed"
                    />
                    {persons.isLoading ? (
                        <li className="space-y-2 p-2">
                            {[...Array(4)].map((_, i) => (
                                <Skeleton key={i} className="h-6" />
                            ))}
                        </li>
                    ) : null}
                    {visiblePersons.map(p => (
                        <PersonItem
                            key={p.id}
                            icon={<UserRoundIcon />}
                            label={<EntityChip entity={p} size="sm" />}
                            count={p.usage?.attributes}
                            selected={person === p.id}
                            onSelect={() => onSelectPerson(p.id)}
                            testId="faces-person"
                        />
                    ))}
                    {persons.isSuccess && visiblePersons.length === 0 ? (
                        <li className="px-3 py-2 text-xs text-muted-foreground">
                            {personQuery
                                ? t('common.no_match', 'No match')
                                : t('faces.no_person', 'Nobody named yet')}
                        </li>
                    ) : null}
                    {persons.hasNextPage ? (
                        <li className="p-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                loading={persons.isFetchingNextPage}
                                onClick={() => void persons.fetchNextPage()}
                            >
                                {t('common.load_more', 'Load more')}
                            </Button>
                        </li>
                    ) : null}
                </ul>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex shrink-0 flex-wrap items-center gap-2 border-b px-3 py-2">
                    {selectedPerson ? (
                        <PersonHeader
                            person={selectedPerson}
                            listId={listId}
                            onChanged={invalidateAll}
                        />
                    ) : (
                        <span className="text-sm font-medium">
                            {grouped
                                ? t('faces.unnamed', 'Unnamed')
                                : t('faces.all_faces', 'All faces')}
                        </span>
                    )}
                    {total !== undefined ? (
                        <Badge variant="muted" data-testid="faces-count">
                            {t('faces.count', {
                                count: total,
                                defaultValue_one: '{{count}} face',
                                defaultValue_other: '{{count}} faces',
                            })}
                        </Badge>
                    ) : null}
                    <div className="flex-1" />
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={refresh}
                        loading={faces.isRefetching || persons.isRefetching}
                    >
                        <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                    </Button>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto">
                    {!definition ? (
                        <EmptyState
                            className="h-full"
                            testId="faces-empty"
                            icon={<ScanFaceIcon />}
                            title={t('faces.empty', 'No face here')}
                            description={t(
                                'faces.not_configured',
                                'The integration needs an entity list and an entity attribute to store the persons.'
                            )}
                        />
                    ) : faces.isLoading || (grouped && unknowns.isLoading) ? (
                        <div className="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3 p-3">
                            {[...Array(8)].map((_, i) => (
                                <Skeleton key={i} className="aspect-[3/4]" />
                            ))}
                        </div>
                    ) : grouped ? (
                        unknownItems.length === 0 ? (
                            <EmptyState
                                className="h-full"
                                testId="faces-empty"
                                icon={<ScanFaceIcon />}
                                title={t('faces.empty', 'No face here')}
                                description={t(
                                    'faces.all_named_help',
                                    'Every face has been named.'
                                )}
                            />
                        ) : (
                            <div
                                className="flex flex-col gap-3 p-3"
                                data-testid="faces-groups"
                            >
                                {unknownItems.map(p => (
                                    <PersonGroup
                                        key={p.id}
                                        person={p}
                                        faces={cards.filter(
                                            f => f.entity?.id === p.id
                                        )}
                                        listId={listId}
                                        canEdit
                                        onNamed={onNamed}
                                        onMerge={onMerge}
                                        onAssign={onAssign}
                                        onRemove={onRemove}
                                    />
                                ))}
                                {unknowns.hasNextPage ? (
                                    <div className="flex justify-center">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            loading={
                                                unknowns.isFetchingNextPage
                                            }
                                            onClick={() =>
                                                void unknowns.fetchNextPage()
                                            }
                                        >
                                            {t('common.load_more', 'Load more')}
                                        </Button>
                                    </div>
                                ) : null}
                            </div>
                        )
                    ) : cards.length === 0 ? (
                        <EmptyState
                            className="h-full"
                            testId="faces-empty"
                            icon={<ScanFaceIcon />}
                            title={t('faces.empty', 'No face here')}
                            description={
                                person
                                    ? t(
                                          'faces.empty_selection_help',
                                          'No face matches the selection.'
                                      )
                                    : t(
                                          'faces.empty_help',
                                          'Faces show up once the face recognition integration has analyzed the assets of a workspace.'
                                      )
                            }
                        />
                    ) : (
                        <>
                            <div
                                className="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3 p-3"
                                data-testid="faces-grid"
                            >
                                {cards.map(face => (
                                    <FaceCard
                                        key={face.id}
                                        face={face}
                                        listId={listId}
                                        canEdit={canEdit(face)}
                                        onAssign={onAssign}
                                        onRemove={onRemove}
                                    />
                                ))}
                            </div>
                            {faces.hasNextPage ? (
                                <div className="flex justify-center p-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        loading={faces.isFetchingNextPage}
                                        onClick={() =>
                                            void faces.fetchNextPage()
                                        }
                                    >
                                        {t('common.load_more', 'Load more')}
                                    </Button>
                                </div>
                            ) : null}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * The selected person: rename it (and its note), or merge it into another one.
 */
function PersonHeader({
    person,
    listId,
    onChanged,
}: {
    person: AttributeEntity;
    listId: string;
    onChanged: () => void;
}) {
    const {t} = useTranslation();
    const [mode, setMode] = useState<'view' | 'rename' | 'merge'>('view');
    const [value, setValue] = useState(person.value);
    const [note, setNote] = useState(person.note ?? '');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setValue(person.value);
        setNote(person.note ?? '');
        setMode('view');
    }, [person]);

    const rename = async () => {
        setSaving(true);
        try {
            await putAttributeEntity(person.id, {
                value: value.trim(),
                note: note.trim() || null,
            });
            toast.success(t('entity.saved', 'Value saved'));
            setMode('view');
            onChanged();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    const merge = async (targetId: string) => {
        setSaving(true);
        try {
            const target = await mergeAttributeEntities(targetId, [person.id]);
            toast.success(
                t('faces.merged_toast', {
                    defaultValue: 'Merged into {{name}}',
                    name: target.value,
                })
            );
            setMode('view');
            onChanged();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    if (mode === 'rename') {
        return (
            <form
                className="flex flex-wrap items-center gap-2"
                data-testid="person-rename"
                onSubmit={e => {
                    e.preventDefault();
                    void rename();
                }}
            >
                <Input
                    value={value}
                    onChange={e => setValue(e.target.value)}
                    aria-label={t('entity.value', 'Value')}
                    className="h-8 w-48 text-sm"
                    autoFocus
                    data-testid="person-rename-value"
                />
                <Input
                    value={note}
                    onChange={e => setNote(e.target.value)}
                    placeholder={t('entity.note', 'Note')}
                    aria-label={t('entity.note', 'Note')}
                    className="h-8 w-48 text-sm"
                    data-testid="person-rename-note"
                />
                <Button
                    type="submit"
                    size="sm"
                    loading={saving}
                    disabled={!value.trim()}
                >
                    {t('common.save', 'Save')}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => setMode('view')}
                >
                    {t('common.cancel', 'Cancel')}
                </Button>
            </form>
        );
    }

    if (mode === 'merge') {
        return (
            <div
                className="flex flex-wrap items-center gap-2"
                data-testid="person-merge"
            >
                <span className="text-sm">
                    {t('faces.merge_into', {
                        defaultValue: 'Merge {{name}} into…',
                        name: person.value,
                    })}
                </span>
                <div className="w-64">
                    <AttributeEntitySelect
                        listId={listId}
                        value={undefined}
                        onChange={v => {
                            if (v && v !== person.id) {
                                void merge(v);
                            }
                        }}
                        disabled={saving}
                    />
                </div>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setMode('view')}
                >
                    {t('common.cancel', 'Cancel')}
                </Button>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-2" data-testid="person-header">
            <UserRoundIcon className="size-4 text-muted-foreground" />
            <span className="text-sm font-medium">
                <EntityChip entity={person} size="sm" />
            </span>
            {person.usage?.humanAttributes !== undefined ? (
                <span className="text-xs text-muted-foreground">
                    {t('faces.references', {
                        count: person.usage.humanAttributes,
                        defaultValue_one: '{{count}} reference face',
                        defaultValue_other: '{{count}} reference faces',
                    })}
                </span>
            ) : null}
            <Button
                size="sm"
                variant="ghost"
                onClick={() => setMode('rename')}
                data-testid="person-rename-button"
            >
                <PencilIcon /> {t('common.rename', 'Rename')}
            </Button>
            <Button
                size="sm"
                variant="ghost"
                onClick={() => setMode('merge')}
                data-testid="person-merge-button"
            >
                <GitMergeIcon /> {t('faces.merge', 'Merge into…')}
            </Button>
        </div>
    );
}

function PersonItem({
    icon,
    label,
    count,
    selected,
    onSelect,
    testId,
}: {
    icon: React.ReactNode;
    label: React.ReactNode;
    count?: number;
    selected: boolean;
    onSelect: () => void;
    testId: string;
}) {
    return (
        <li>
            <button
                type="button"
                data-testid={testId}
                aria-pressed={selected}
                className={cn(
                    'flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent [&>svg]:size-4 [&>svg]:shrink-0 [&>svg]:text-muted-foreground',
                    selected && 'bg-primary/10 font-medium'
                )}
                onClick={onSelect}
            >
                {icon}
                <span className="min-w-0 flex-1 truncate">{label}</span>
                {count !== undefined ? (
                    <span className="text-xs text-muted-foreground">
                        {count}
                    </span>
                ) : null}
            </button>
        </li>
    );
}

export type {Asset};
