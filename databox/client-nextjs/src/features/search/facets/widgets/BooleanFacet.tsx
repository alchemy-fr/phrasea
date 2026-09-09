'use client';

import {useTranslation} from 'react-i18next';
import type {FacetWidgetProps} from '../FacetsPanel';
import {useFacetCondition} from '../useFacetCondition';
import {resolveBucket} from './ListFacet';
import {Checkbox} from '@/components/ui/controls';
import {formatNumber} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';

export function BooleanFacet({name, facet}: FacetWidgetProps) {
    const {t, i18n} = useTranslation();
    const {hasValue, toggleValue} = useFacetCondition(name);

    return (
        <ul className="space-y-0.5">
            {facet.buckets.map(bucket => {
                const {value} = resolveBucket(bucket);
                const bool =
                    value === true ||
                    value === 1 ||
                    value === 'true' ||
                    value === '1';
                const selected = hasValue(bool);

                return (
                    <li key={String(value)}>
                        <label
                            className={cn(
                                'flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-accent/60',
                                selected && 'bg-primary/10'
                            )}
                        >
                            <Checkbox
                                checked={selected}
                                onCheckedChange={() => toggleValue(bool)}
                            />
                            <span className="flex-1">
                                {bool
                                    ? t('common.yes', 'Yes')
                                    : t('common.no', 'No')}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatNumber(bucket.doc_count, i18n.language)}
                            </span>
                        </label>
                    </li>
                );
            })}
        </ul>
    );
}
