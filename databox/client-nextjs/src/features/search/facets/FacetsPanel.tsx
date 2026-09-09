'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
    ChevronDownIcon,
    ChevronsDownUpIcon,
    ChevronsUpDownIcon,
    EyeIcon,
    EyeOffIcon,
    MoreVerticalIcon,
    PinIcon,
    Settings2Icon,
} from 'lucide-react';
import {Facet, FacetType} from '@/types/api';
import {useResults} from '../ResultProvider';
import {Input} from '@/components/ui/input';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {Tooltip} from '@/components/ui/overlays';
import {
    FacetPreference,
    usePreferencesStore,
} from '@/features/preferences/store';
import {ListFacet} from './widgets/ListFacet';
import {BooleanFacet} from './widgets/BooleanFacet';
import {DateHistogramFacet} from './widgets/DateHistogramFacet';
import {GeoDistanceFacet} from './widgets/GeoDistanceFacet';
import {useModals} from '@/components/modals/ModalProvider';
import {FacetSettingsDialog} from './FacetSettingsDialog';
import {cn} from '@/lib/utils/cn';
import {Skeleton} from '@/components/ui/misc';

const EMPTY_FACETS: never[] = [];
export type FacetWidgetProps = {name: string; facet: Facet};

const ORDER_INFINITY = 999999;

export function FacetsPanel() {
    const {t} = useTranslation();
    const {facets, loading} = useResults();
    const prefs =
        usePreferencesStore(s => s.preferences.facets) ?? EMPTY_FACETS;
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const {openModal} = useModals();
    const [filter, setFilter] = useState('');
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
    const [allCollapsed, setAllCollapsed] = useState<boolean | undefined>();

    const entries = useMemo(() => {
        const list = Object.entries(facets ?? {}).map(([name, facet]) => {
            const pref = prefs.find(p => p.name === name);

            return {
                name,
                facet,
                hidden: !!pref?.hidden,
                pinned: pref && !pref.hidden && pref.order !== undefined,
                order: pref?.order ?? ORDER_INFINITY,
            };
        });
        list.sort(
            (a, b) =>
                a.order - b.order ||
                a.facet.meta.displayName.localeCompare(b.facet.meta.displayName)
        );

        return list;
    }, [facets, prefs]);

    const filtering = filter.trim().length > 0;
    const visible = entries.filter(e => {
        if (!filtering && e.hidden) {
            return false;
        }

        return (
            !filtering ||
            e.facet.meta.displayName
                .toLowerCase()
                .includes(filter.toLowerCase())
        );
    });

    const setFacetPrefs = (
        fn: (prev: FacetPreference[]) => FacetPreference[]
    ) => updatePreference('facets', prev => fn(prev ?? []));

    const hide = (name: string) => {
        const previous = prefs.find(p => p.name === name);
        void setFacetPrefs(prev =>
            prev.some(p => p.name === name)
                ? prev.map(p => (p.name === name ? {...p, hidden: true} : p))
                : [...prev, {name, hidden: true}]
        );
        toast(t('facets.hidden', 'Facet hidden'), {
            action: {
                label: t('common.undo', 'Undo'),
                onClick: () =>
                    setFacetPrefs(prev =>
                        previous
                            ? prev.map(p => (p.name === name ? previous : p))
                            : prev.filter(p => p.name !== name)
                    ),
            },
        });
    };

    const unhide = (name: string) =>
        setFacetPrefs(prev => prev.filter(p => p.name !== name));

    const togglePin = (name: string) =>
        setFacetPrefs(prev => {
            const nextOrder = Math.max(-1, ...prev.map(p => p.order ?? -1)) + 1;
            const existing = prev.find(p => p.name === name);
            if (existing) {
                if (existing.hidden) {
                    return prev.map(p =>
                        p.name === name ? {name, order: nextOrder} : p
                    );
                }

                return prev.filter(p => p.name !== name);
            }

            return [...prev, {name, order: nextOrder}];
        });

    const isOpen = (name: string) =>
        allCollapsed !== undefined ? !allCollapsed : !collapsed[name];
    const toggleOpen = (name: string) => {
        setAllCollapsed(undefined);
        setCollapsed(prev => ({...prev, [name]: isOpen(name)}));
    };

    if (!facets && loading) {
        return (
            <div className="space-y-3 p-3">
                {[...Array(5)].map((_, i) => (
                    <Skeleton key={i} className="h-8" />
                ))}
            </div>
        );
    }

    return (
        <div className="flex flex-col">
            <div className="flex items-center gap-1 px-2 pb-2">
                <Input
                    value={filter}
                    onChange={e => setFilter(e.target.value)}
                    placeholder={t('common.filter', 'Filter…')}
                    className="h-8"
                />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.more', 'More')}
                        >
                            <MoreVerticalIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={() => setAllCollapsed(false)}
                        >
                            <ChevronsUpDownIcon />{' '}
                            {t('facets.expand_all', 'Expand all')}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => setAllCollapsed(true)}
                        >
                            <ChevronsDownUpIcon />{' '}
                            {t('facets.collapse_all', 'Collapse all')}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() =>
                                openModal(FacetSettingsDialog, {
                                    facets: facets ?? {},
                                })
                            }
                        >
                            <Settings2Icon />{' '}
                            {t('facets.settings', 'Facet settings…')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            {visible.length === 0 ? (
                <p className="px-3 py-4 text-center text-xs text-muted-foreground">
                    {filtering
                        ? t('common.no_match', 'No match')
                        : t('facets.empty', 'No facet for this search')}
                </p>
            ) : null}
            {visible.map(({name, facet, hidden, pinned}) => (
                <div key={name} className="group/facet border-b">
                    <div className="flex items-center pr-1">
                        <button
                            type="button"
                            className={cn(
                                'flex min-w-0 flex-1 items-center gap-1.5 px-3 py-2 text-left text-sm font-medium hover:bg-accent/60',
                                hidden && 'text-destructive'
                            )}
                            onClick={() => toggleOpen(name)}
                        >
                            <ChevronDownIcon
                                className={cn(
                                    'size-4 shrink-0 text-muted-foreground transition-transform',
                                    !isOpen(name) && '-rotate-90'
                                )}
                            />
                            <span className="truncate">
                                {facet.meta.displayName}
                                {facet.meta.locale ? (
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        ({facet.meta.locale})
                                    </span>
                                ) : null}
                            </span>
                        </button>
                        <span className="flex items-center opacity-0 transition-opacity group-hover/facet:opacity-100 focus-within:opacity-100">
                            <Tooltip
                                content={
                                    pinned
                                        ? t('facets.unpin', 'Unpin')
                                        : t('facets.pin', 'Pin to top')
                                }
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={() => togglePin(name)}
                                    className={cn(
                                        pinned && 'text-primary opacity-100'
                                    )}
                                >
                                    <PinIcon />
                                </Button>
                            </Tooltip>
                            <Tooltip
                                content={
                                    hidden
                                        ? t('facets.show', 'Show facet')
                                        : t('facets.hide', 'Hide facet')
                                }
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={() =>
                                        hidden ? unhide(name) : hide(name)
                                    }
                                >
                                    {hidden ? <EyeIcon /> : <EyeOffIcon />}
                                </Button>
                            </Tooltip>
                        </span>
                    </div>
                    {isOpen(name) ? (
                        <div className="px-2 pb-2">
                            <FacetWidget name={name} facet={facet} />
                        </div>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

function FacetWidget({name, facet}: FacetWidgetProps) {
    switch (facet.meta.widget) {
        case FacetType.Boolean:
            return <BooleanFacet name={name} facet={facet} />;
        case FacetType.DateRange:
            return <DateHistogramFacet name={name} facet={facet} />;
        case FacetType.GeoDistance:
            return <GeoDistanceFacet name={name} facet={facet} />;
        case FacetType.Entity:
        case FacetType.Text:
        default:
            return <ListFacet name={name} facet={facet} />;
    }
}
