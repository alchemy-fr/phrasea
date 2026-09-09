'use client';

import {useTranslation} from 'react-i18next';
import {AttributeType, FacetBucket, LabelledBucketValue} from '@/types/api';
import type {FacetWidgetProps} from '../FacetsPanel';
import {useFacetCondition} from '../useFacetCondition';
import {Checkbox} from '@/components/ui/controls';
import {formatAttributeString} from '@/features/attributes/types/registry';
import {useFormatContext} from '@/features/attributes/AttributeValue';
import {EntityChip, TagChip} from '@/components/chips';
import {formatNumber} from '@/lib/utils/format';
import type {ScalarValue} from '../../aql/types';
import {cn} from '@/lib/utils/cn';

export function resolveBucket(bucket: FacetBucket): LabelledBucketValue {
    const key = bucket.key;
    if (key && typeof key === 'object' && 'value' in key) {
        return key;
    }

    return {
        label: String(key),
        value: key as ScalarValue as LabelledBucketValue['value'],
    };
}

export function ListFacet({name, facet}: FacetWidgetProps) {
    const {t, i18n} = useTranslation();
    const ctx = useFormatContext();
    const {hasValue, toggleValue, builder, active, toggleMissing} =
        useFacetCondition(name);
    const type = facet.meta.type;

    return (
        <ul className="space-y-0.5">
            {facet.buckets.map(bucket => {
                const {label, value, item} = resolveBucket(bucket);
                const selected = hasValue(value as ScalarValue);
                const display =
                    type && !item && typeof value !== 'object'
                        ? formatAttributeString(type, value, undefined, ctx) ||
                          label
                        : label;

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
                                onCheckedChange={() =>
                                    toggleValue(value as ScalarValue)
                                }
                            />
                            <span className="min-w-0 flex-1 truncate">
                                <BucketLabel
                                    type={type}
                                    item={item}
                                    label={display}
                                />
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatNumber(bucket.doc_count, i18n.language)}
                            </span>
                        </label>
                    </li>
                );
            })}
            {facet.missing_count ? (
                <li>
                    <label
                        className={cn(
                            'flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm italic hover:bg-accent/60',
                            active && builder.includeMissing && 'bg-primary/10'
                        )}
                    >
                        <Checkbox
                            checked={active && builder.includeMissing}
                            onCheckedChange={toggleMissing}
                        />
                        <span className="min-w-0 flex-1 truncate text-muted-foreground">
                            {t('facets.missing', 'Missing')}
                        </span>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {formatNumber(facet.missing_count, i18n.language)}
                        </span>
                    </label>
                </li>
            ) : null}
            {facet.sum_other_doc_count ? (
                <li className="px-1.5 py-1 text-xs text-muted-foreground">
                    {t('facets.others', '+ {{count}} in other values', {
                        count: facet.sum_other_doc_count,
                    })}
                </li>
            ) : null}
        </ul>
    );
}

function BucketLabel({
    type,
    item,
    label,
}: {
    type?: AttributeType;
    item?: Record<string, unknown>;
    label: string;
}) {
    if (item) {
        if (type === AttributeType.Tag) {
            return (
                <TagChip
                    tag={{...(item as any), displayName: label, name: label}}
                />
            );
        }
        if (type === AttributeType.Entity) {
            return (
                <EntityChip
                    entity={{...(item as any), value: label}}
                    size="sm"
                />
            );
        }
        if ((item as any).color) {
            return (
                <span className="inline-flex items-center gap-1.5">
                    <span
                        className="inline-block size-2.5 rounded-full"
                        style={{backgroundColor: (item as any).color as string}}
                    />
                    {label}
                </span>
            );
        }
    }

    return <>{label}</>;
}
