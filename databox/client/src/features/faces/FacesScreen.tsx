'use client';

import {useCallback, useEffect, useMemo, useState} from 'react';
import {useSearchParams} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {
    useInfiniteQuery,
    useQuery,
    useQueryClient,
    type InfiniteData,
} from '@tanstack/react-query';
import {toast} from 'sonner';
import {
    RefreshCwIcon,
    ScanFaceIcon,
    UserRoundIcon,
    UsersIcon,
} from 'lucide-react';
import type {AssetFace, Page} from '@/types/api';
import {getFaceIdentities, getFaces, identifyFace} from '@/lib/api/faces';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {routes} from '@/lib/routes';
import {Button} from '@/components/ui/button';
import {FilterInput} from '@/components/ui/filter-input';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {SimpleSelect} from '@/components/ui/select';
import {usePageTrail} from '@/components/layout/layoutStore';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {cn} from '@/lib/utils/cn';
import {FaceCard} from './FaceCard';

const pageSize = 40;

/** Value of the person filter listing the faces without a name */
const unnamedPerson = '\0unnamed';

export const facesQueryKey = 'faces';
export const faceIdentitiesQueryKey = 'face-identities';

type Filters = {
    workspace?: string;
    /** A person's name, `unnamedPerson`, or undefined for every face */
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
 * persons already named on the left (with the faces still unnamed), the
 * faces of the selection on the right, each one named in place. Reserved to
 * the face editors (`edit_faces` role) and the admins.
 */
export function FacesScreen() {
    const {t} = useTranslation();
    const {hasRole} = useAuth();
    const allowed =
        hasRole(AppRole.EditFaces) ||
        hasRole(AppRole.DataboxAdmin) ||
        hasRole(AppRole.Admin);

    usePageTrail(t('faces.title', 'Faces'), routes.faces());

    return (
        <RequireAuth>
            {allowed ? (
                <FacesBrowser />
            ) : (
                <EmptyState
                    className="h-full"
                    testId="faces-forbidden"
                    icon={<ScanFaceIcon />}
                    title={t(
                        'common.forbidden',
                        'You are not allowed to access this page'
                    )}
                    description={t(
                        'faces.forbidden_help',
                        'Naming faces requires the "edit_faces" role.'
                    )}
                />
            )}
        </RequireAuth>
    );
}

function FacesBrowser() {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const searchParams = useSearchParams();
    const [filters, setFilters] = useState<Filters>(() =>
        filtersFromParams(searchParams)
    );
    const [personQuery, setPersonQuery] = useState('');

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

    const identities = useQuery({
        queryKey: [faceIdentitiesQueryKey, filters.workspace],
        queryFn: ({signal}) =>
            getFaceIdentities({workspace: filters.workspace}, signal),
    });

    const faces = useInfiniteQuery({
        queryKey: [facesQueryKey, filters.workspace, filters.person],
        queryFn: ({pageParam, signal}) =>
            getFaces(
                {
                    url: pageParam,
                    workspace: filters.workspace,
                    identity:
                        filters.person && filters.person !== unnamedPerson
                            ? filters.person
                            : undefined,
                    identified:
                        filters.person === unnamedPerson ? false : undefined,
                    limit: pageSize,
                },
                signal
            ),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });

    const items = useMemo(
        () => faces.data?.pages.flatMap(p => p.items) ?? [],
        [faces.data]
    );
    const total = faces.data?.pages[0]?.total;

    const knownNames = useMemo(
        () => identities.data?.identities.map(i => i.identity) ?? [],
        [identities.data]
    );
    const visibleIdentities = useMemo(() => {
        const q = personQuery.trim().toLowerCase();
        const all = identities.data?.identities ?? [];

        return q ? all.filter(i => i.identity.toLowerCase().includes(q)) : all;
    }, [identities.data, personQuery]);

    const refresh = () => {
        void identities.refetch();
        void faces.refetch();
    };

    const onIdentify = async (face: AssetFace, identity: string | null) => {
        try {
            const updated = await identifyFace(face.id, identity);
            // The face keeps its place in the list, whatever the filter: the
            // user sees what they just did, the next refresh reorders.
            queryClient.setQueriesData<InfiniteData<Page<AssetFace>>>(
                {queryKey: [facesQueryKey]},
                data =>
                    data && {
                        ...data,
                        pages: data.pages.map(page => ({
                            ...page,
                            items: page.items.map(f =>
                                f.id === updated.id ? {...f, ...updated} : f
                            ),
                        })),
                    }
            );
            void queryClient.invalidateQueries({
                queryKey: [faceIdentitiesQueryKey],
            });
            toast.success(
                identity
                    ? t('faces.named_toast', {
                          defaultValue: 'Face named {{name}}',
                          name: updated.identity ?? identity,
                      })
                    : t('faces.name_removed', 'Name removed')
            );
        } catch (e: any) {
            toast.error(
                e?.message ??
                    t('faces.name_error', 'The face could not be named')
            );
            throw e;
        }
    };

    const workspaceOptions = useMemo(
        () => [
            {value: '', label: t('faces.all_workspaces', 'All workspaces')},
            ...workspaces.map(w => ({value: w.id, label: w.displayName})),
        ],
        [workspaces, t]
    );

    return (
        <div className="flex h-full min-h-0 flex-col">
            <header className="flex shrink-0 flex-wrap items-center gap-2 border-b px-3 py-2">
                <ScanFaceIcon className="size-5 text-primary" />
                <h1 className="font-semibold">{t('faces.title', 'Faces')}</h1>
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
                <SimpleSelect
                    size="sm"
                    className="w-56"
                    value={filters.workspace ?? ''}
                    onValueChange={value =>
                        update({
                            workspace: value || undefined,
                            person: undefined,
                        })
                    }
                    options={workspaceOptions}
                    id="faces-workspace"
                />
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={refresh}
                    loading={faces.isRefetching || identities.isRefetching}
                >
                    <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                </Button>
            </header>

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
                            selected={!filters.person}
                            onSelect={() => update({person: undefined})}
                            testId="faces-person-all"
                        />
                        <PersonItem
                            icon={<ScanFaceIcon />}
                            label={t('faces.unnamed', 'Unnamed')}
                            count={identities.data?.unnamed}
                            selected={filters.person === unnamedPerson}
                            onSelect={() => update({person: unnamedPerson})}
                            testId="faces-person-unnamed"
                        />
                        {identities.isLoading ? (
                            <li className="space-y-2 p-2">
                                {[...Array(4)].map((_, i) => (
                                    <Skeleton key={i} className="h-6" />
                                ))}
                            </li>
                        ) : null}
                        {visibleIdentities.map(person => (
                            <PersonItem
                                key={person.identity}
                                icon={<UserRoundIcon />}
                                label={person.identity}
                                count={person.faces}
                                selected={filters.person === person.identity}
                                onSelect={() =>
                                    update({person: person.identity})
                                }
                                testId="faces-person"
                            />
                        ))}
                        {identities.isSuccess &&
                        visibleIdentities.length === 0 ? (
                            <li className="px-3 py-2 text-xs text-muted-foreground">
                                {personQuery
                                    ? t('common.no_match', 'No match')
                                    : t('faces.no_person', 'Nobody named yet')}
                            </li>
                        ) : null}
                    </ul>
                </aside>

                <div className="min-w-0 flex-1 overflow-y-auto">
                    {faces.isLoading ? (
                        <div className="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3 p-3">
                            {[...Array(8)].map((_, i) => (
                                <Skeleton key={i} className="aspect-[3/4]" />
                            ))}
                        </div>
                    ) : items.length === 0 ? (
                        <EmptyState
                            className="h-full"
                            testId="faces-empty"
                            icon={<ScanFaceIcon />}
                            title={t('faces.empty', 'No face here')}
                            description={
                                filters.person
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
                                {items.map(face => (
                                    <FaceCard
                                        key={face.id}
                                        face={face}
                                        knownNames={knownNames}
                                        onIdentify={onIdentify}
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

function PersonItem({
    icon,
    label,
    count,
    selected,
    onSelect,
    testId,
}: {
    icon: React.ReactNode;
    label: string;
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
