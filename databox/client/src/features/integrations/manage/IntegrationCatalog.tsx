'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CheckIcon, SearchIcon} from 'lucide-react';
import type {
    IntegrationCategory,
    IntegrationType,
    WorkspaceIntegration,
} from '@/types/api';
import {Input} from '@/components/ui/input';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {cn} from '@/lib/utils/cn';
import {
    categoryLabel,
    featureLabel,
    integrationCategories,
    IntegrationTypeIcon,
    mainCategory,
} from './integrationTypeUi';

type Props = {
    types: IntegrationType[] | undefined;
    /** The integrations already set up, to flag the types in use */
    existing: WorkspaceIntegration[];
    /** Instance-wide catalog: only the types not requiring a workspace */
    instance?: boolean;
    onSelect: (type: IntegrationType) => void;
};

/**
 * Catalog of the available integration types, grouped by category, shown
 * when a new integration is added.
 */
export function IntegrationCatalog({
    types,
    existing,
    instance,
    onSelect,
}: Props) {
    const {t} = useTranslation();
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState<IntegrationCategory | null>(null);

    const available = useMemo(
        () => (types ?? []).filter(tp => !instance || !tp.requiresWorkspace),
        [types, instance]
    );

    const usage = useMemo(() => {
        const counts: Record<string, number> = {};
        for (const i of existing) {
            counts[i.integration] = (counts[i.integration] ?? 0) + 1;
        }

        return counts;
    }, [existing]);

    const groups = useMemo(() => {
        const q = query.trim().toLowerCase();
        const visible = available.filter(
            tp =>
                (!category || tp.categories.includes(category)) &&
                (!q ||
                    tp.displayName.toLowerCase().includes(q) ||
                    tp.name.toLowerCase().includes(q) ||
                    tp.description.toLowerCase().includes(q))
        );

        return integrationCategories
            .map(c => ({
                category: c,
                types: visible
                    // Sections by main category, unless filtered on another one
                    .filter(tp => (category ?? mainCategory(tp)) === c)
                    .sort((a, b) => a.displayName.localeCompare(b.displayName)),
            }))
            .filter(g => g.types.length > 0);
    }, [available, category, query]);

    const usedCategories = integrationCategories.filter(c =>
        available.some(tp => tp.categories.includes(c))
    );

    if (!types) {
        return (
            <div className="grid gap-3 sm:grid-cols-2">
                {[...Array(6)].map((_, i) => (
                    <Skeleton key={i} className="h-28" />
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-4" data-testid="integration-catalog">
            <div>
                <h3 className="text-base font-semibold">
                    {t('integration.catalog.title', 'Add an integration')}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {instance
                        ? t(
                              'integration.catalog.instance_intro',
                              'Instance-wide integrations are available in every workspace. Only the integrations that do not depend on a workspace are listed.'
                          )
                        : t(
                              'integration.catalog.intro',
                              'Pick the integration to set up in this workspace.'
                          )}
                </p>
            </div>
            <div className="relative">
                <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={t(
                        'integration.catalog.search',
                        'Search an integration…'
                    )}
                    className="pl-8"
                    autoFocus
                />
            </div>
            {usedCategories.length > 1 ? (
                <div className="flex flex-wrap gap-1.5">
                    <CategoryPill
                        active={category === null}
                        onClick={() => setCategory(null)}
                    >
                        {t('integration.catalog.all', 'All')}
                    </CategoryPill>
                    {usedCategories.map(c => (
                        <CategoryPill
                            key={c}
                            active={category === c}
                            onClick={() =>
                                setCategory(category === c ? null : c)
                            }
                        >
                            {categoryLabel(t, c)}
                        </CategoryPill>
                    ))}
                </div>
            ) : null}
            {groups.length === 0 ? (
                <EmptyState
                    title={t(
                        'integration.catalog.empty',
                        'No integration matches your search'
                    )}
                />
            ) : (
                groups.map(g => (
                    <section key={g.category} className="space-y-2">
                        <h4 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {categoryLabel(t, g.category)}
                        </h4>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {g.types.map(tp => (
                                <CatalogCard
                                    key={tp.id}
                                    type={tp}
                                    used={usage[tp.name] ?? 0}
                                    instance={instance}
                                    onSelect={() => onSelect(tp)}
                                />
                            ))}
                        </div>
                    </section>
                ))
            )}
        </div>
    );
}

function CategoryPill({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'h-7 rounded-full border px-3 text-xs transition-colors',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'bg-background hover:bg-accent'
            )}
        >
            {children}
        </button>
    );
}

function CatalogCard({
    type,
    used,
    instance,
    onSelect,
}: {
    type: IntegrationType;
    used: number;
    instance?: boolean;
    onSelect: () => void;
}) {
    const {t} = useTranslation();

    return (
        <button
            type="button"
            data-testid="integration-catalog-item"
            data-integration={type.name}
            onClick={onSelect}
            className="group/card flex h-full flex-col gap-2 rounded-lg border bg-card p-3 text-left transition-colors hover:border-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
        >
            <div className="flex items-start gap-3">
                <IntegrationTypeIcon type={type} />
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="truncate font-medium">
                            {type.displayName}
                        </span>
                        {used > 0 ? (
                            <Badge variant="success" className="ml-auto">
                                <CheckIcon />
                                {used > 1
                                    ? t(
                                          'integration.catalog.used_count',
                                          'Added ×{{count}}',
                                          {count: used}
                                      )
                                    : t('integration.catalog.used', 'Added')}
                            </Badge>
                        ) : null}
                    </div>
                    <div className="truncate font-mono text-[11px] text-muted-foreground">
                        {type.name}
                    </div>
                </div>
            </div>
            {type.description ? (
                <p className="line-clamp-3 text-sm text-muted-foreground">
                    {type.description}
                </p>
            ) : null}
            <div className="mt-auto flex flex-wrap gap-1 pt-1">
                {type.categories.slice(1).map(c => (
                    <Badge key={c} variant="outline">
                        {categoryLabel(t, c)}
                    </Badge>
                ))}
                {type.features.map(f => (
                    <Badge key={f} variant="muted">
                        {featureLabel(t, f)}
                    </Badge>
                ))}
                {!instance && !type.requiresWorkspace ? (
                    <Badge variant="outline">
                        {t(
                            'integration.catalog.instance_capable',
                            'Instance-wide possible'
                        )}
                    </Badge>
                ) : null}
            </div>
        </button>
    );
}
