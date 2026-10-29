'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {ChevronDownIcon, ChevronRightIcon} from 'lucide-react';
import {AttributeType, type Facet, type FacetBucket} from '@/types/api';
import type {FacetWidgetProps} from '../FacetsPanel';
import {useFacetCondition} from '../useFacetCondition';
import {resolveBucket} from '../buckets';
import {
    buildBucketTree,
    isBucketTree,
    someInBranch,
    type BucketNode,
} from '../bucketTree';
import {Checkbox} from '@/components/ui/controls';
import {formatAttributeString} from '@/features/attributes/types/registry';
import {useFormatContext} from '@/features/attributes/AttributeValue';
import {EntityChip, TagChip} from '@/components/chips';
import {formatNumber} from '@/lib/utils/format';
import type {ScalarValue} from '../../aql/types';
import {cn} from '@/lib/utils/cn';

export function ListFacet({name, facet}: FacetWidgetProps) {
    const {t, i18n} = useTranslation();
    const {hasValue, toggleValue, builder, active, toggleMissing} =
        useFacetCondition(name);
    const tree = buildBucketTree(facet.buckets);
    const isSelected = (value: string) => hasValue(value as ScalarValue);
    const toggle = (value: string) => toggleValue(value as ScalarValue);

    return (
        <ul className="space-y-0.5">
            {isBucketTree(tree)
                ? tree.map(node => (
                      <BucketTreeNode
                          key={node.value}
                          node={node}
                          facet={facet}
                          isSelected={isSelected}
                          toggle={toggle}
                      />
                  ))
                : facet.buckets.map(bucket => {
                      const value = String(resolveBucket(bucket).value);

                      return (
                          <li key={value}>
                              <BucketRow
                                  bucket={bucket}
                                  facet={facet}
                                  selected={isSelected(value)}
                                  onToggle={() => toggle(value)}
                              />
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

/**
 * An entity of a hierarchical list: selecting it also finds the assets of its
 * descendants, which are then shown as selected.
 */
function BucketTreeNode({
    node,
    facet,
    isSelected,
    toggle,
    implied = false,
}: {
    node: BucketNode;
    facet: Facet;
    isSelected: (value: string) => boolean;
    toggle: (value: string) => void;
    /** An ancestor is selected */
    implied?: boolean;
}) {
    const {t} = useTranslation();
    const selected = isSelected(node.value);
    const hasChildren = node.children.length > 0;
    const [open, setOpen] = useState(() =>
        node.children.some(c => someInBranch(c, isSelected))
    );

    return (
        <li>
            <div className="flex items-center">
                <button
                    type="button"
                    className="flex size-5 shrink-0 items-center justify-center text-muted-foreground disabled:invisible"
                    disabled={!hasChildren}
                    onClick={() => setOpen(o => !o)}
                    aria-expanded={hasChildren ? open : undefined}
                    aria-label={t('common.toggle', 'Toggle')}
                >
                    {open ? (
                        <ChevronDownIcon className="size-4" />
                    ) : (
                        <ChevronRightIcon className="size-4" />
                    )}
                </button>
                <BucketRow
                    className="min-w-0 flex-1"
                    bucket={node.bucket}
                    facet={facet}
                    selected={selected || implied}
                    // Still unchecked when it was selected before its ancestor
                    disabled={implied && !selected}
                    onToggle={() => toggle(node.value)}
                />
            </div>
            {hasChildren && open ? (
                <ul className="ml-2.5 space-y-0.5 border-l pl-1">
                    {node.children.map(child => (
                        <BucketTreeNode
                            key={child.value}
                            node={child}
                            facet={facet}
                            isSelected={isSelected}
                            toggle={toggle}
                            implied={implied || selected}
                        />
                    ))}
                </ul>
            ) : null}
        </li>
    );
}

function BucketRow({
    bucket,
    facet,
    selected,
    disabled,
    onToggle,
    className,
}: {
    bucket: FacetBucket;
    facet: Facet;
    selected: boolean;
    disabled?: boolean;
    onToggle: () => void;
    className?: string;
}) {
    const {i18n} = useTranslation();
    const ctx = useFormatContext();
    const type = facet.meta.type;
    const {label, value, item} = resolveBucket(bucket);
    // Labelled buckets (entities resolved by the API) already carry their
    // display label: only format raw scalar keys.
    const labelled = bucket.key !== null && typeof bucket.key === 'object';
    const display =
        type && !labelled && typeof value !== 'object'
            ? formatAttributeString(type, value, undefined, ctx) || label
            : label;

    return (
        <label
            data-testid="facet-value"
            data-selected={selected ? 'true' : undefined}
            className={cn(
                'flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-accent/60',
                selected && 'bg-primary/10',
                disabled && 'cursor-default',
                className
            )}
        >
            <Checkbox
                checked={selected}
                disabled={disabled}
                onCheckedChange={onToggle}
            />
            <span className="min-w-0 flex-1 truncate">
                <BucketLabel type={type} item={item} label={display} />
            </span>
            <span className="text-xs text-muted-foreground tabular-nums">
                {formatNumber(bucket.doc_count, i18n.language)}
            </span>
        </label>
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
