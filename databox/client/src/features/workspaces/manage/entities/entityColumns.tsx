import type {ColumnDef} from '@tanstack/react-table';
import type {TFunction} from 'i18next';
import {
    ImagesIcon,
    MoreHorizontalIcon,
    PencilIcon,
    ThumbsDownIcon,
    ThumbsUpIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {Flag} from '@/components/ui/flag';
import {ColorSwatch} from '@/components/chips';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {
    actionsColumn,
    selectionColumn,
} from '@/components/ui/data-table/columns';
import type {CellEditorOption} from '@/components/ui/data-table/types';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';

/** A row added inline, not saved yet: only its value can be typed */
export const DRAFT_PREFIX = 'draft-';

export function isDraft(entity: AttributeEntity): boolean {
    return entity.id.startsWith(DRAFT_PREFIX);
}

export type EntityColumnsOptions = {
    list: EntityList;
    locales: string[];
    t: TFunction;
    onApprove: (e: AttributeEntity) => void;
    onReject: (e: AttributeEntity) => void;
    onPreview: (e: AttributeEntity) => void;
    onEdit: (e: AttributeEntity) => void;
    onDelete: (e: AttributeEntity) => void;
    onDiscardDraft?: (e: AttributeEntity) => void;
};

const TRANSLATIONS = 'translations.';
const SYNONYMS = 'synonyms.';

export const statusOptions = (t: TFunction): CellEditorOption[] => [
    {
        value: String(AttributeEntityStatus.Pending),
        label: t('entity.pending', 'Pending'),
    },
    {
        value: String(AttributeEntityStatus.Approved),
        label: t('entity.approved', 'Approved'),
    },
    {
        value: String(AttributeEntityStatus.Rejected),
        label: t('entity.rejected', 'Rejected'),
    },
];

/** Value of a cell as its editor handles it */
export function cellValueOf(entity: AttributeEntity, columnId: string): string {
    if (columnId.startsWith(TRANSLATIONS)) {
        return entity.translations?.[columnId.slice(TRANSLATIONS.length)] ?? '';
    }
    if (columnId.startsWith(SYNONYMS)) {
        return (entity.synonyms?.[columnId.slice(SYNONYMS.length)] ?? []).join(
            ', '
        );
    }
    switch (columnId) {
        case 'value':
            return entity.value ?? '';
        case 'note':
            return entity.note ?? '';
        case 'emoji':
            return entity.emoji ?? '';
        case 'color':
            return entity.color ?? '';
        case 'status':
            return String(entity.status);
        default:
            return '';
    }
}

/**
 * The PATCH payload of an edited cell; `null` when the value is invalid (an
 * empty value).
 */
export function entityPatchFromCell(
    entity: AttributeEntity,
    columnId: string,
    value: string
): Partial<AttributeEntity> | null {
    if (columnId.startsWith(TRANSLATIONS)) {
        const locale = columnId.slice(TRANSLATIONS.length);
        const translations = {...(entity.translations ?? {})};
        const v = value.trim();
        if (v) {
            translations[locale] = v;
        } else {
            delete translations[locale];
        }

        return {translations};
    }
    if (columnId.startsWith(SYNONYMS)) {
        const locale = columnId.slice(SYNONYMS.length);
        const list = value
            .split(',')
            .map(s => s.trim())
            .filter(Boolean);
        const synonyms = {...(entity.synonyms ?? {})};
        if (list.length > 0) {
            synonyms[locale] = list;
        } else {
            delete synonyms[locale];
        }

        return {synonyms};
    }
    switch (columnId) {
        case 'value': {
            const v = value.trim();

            return v ? {value: v} : null;
        }
        case 'note':
            return {note: value.trim() || null};
        case 'emoji':
            return {emoji: value.trim() || null};
        case 'color':
            return {color: value || null};
        case 'status':
            return {status: Number(value) as AttributeEntityStatus};
        default:
            return null;
    }
}

function StatusBadge({
    status,
    t,
}: {
    status: AttributeEntityStatus;
    t: TFunction;
}) {
    switch (status) {
        case AttributeEntityStatus.Approved:
            return (
                <Badge variant="success">
                    {t('entity.approved', 'Approved')}
                </Badge>
            );
        case AttributeEntityStatus.Pending:
            return (
                <Badge variant="warning">
                    {t('entity.pending', 'Pending')}
                </Badge>
            );
        default:
            return (
                <Badge variant="destructive">
                    {t('entity.rejected', 'Rejected')}
                </Badge>
            );
    }
}

export function buildEntityColumns(
    o: EntityColumnsOptions
): ColumnDef<AttributeEntity, any>[] {
    const {list, locales, t} = o;
    const notDraft = (e: AttributeEntity) => !isDraft(e);
    const columns: ColumnDef<AttributeEntity, any>[] = [
        selectionColumn<AttributeEntity>({
            selectAll: t('table.select_all', 'Select all'),
            selectRow: t('table.select_row', 'Select row'),
        }),
        {
            id: 'value',
            accessorKey: 'value',
            header: () => t('entity.value', 'Value'),
            size: 240,
            meta: {
                editor: 'text',
                filter: 'text',
                filterPlaceholder: t('entity.filter_value', 'Filter values'),
                getValue: e => cellValueOf(e, 'value'),
            },
            cell: ({row}) => {
                const e = row.original;

                return (
                    <span
                        data-testid="cell-value"
                        className={cn(
                            'flex min-w-0 items-center gap-1.5 truncate',
                            e.status === AttributeEntityStatus.Pending &&
                                'italic'
                        )}
                    >
                        {e.color ? (
                            <span
                                className="inline-block size-2.5 shrink-0 rounded-full"
                                style={{backgroundColor: e.color}}
                            />
                        ) : null}
                        {e.emoji ? <span>{e.emoji}</span> : null}
                        <span className="truncate">{e.value}</span>
                    </span>
                );
            },
        },
        {
            id: 'note',
            accessorFn: e => e.note ?? '',
            header: () => t('entity.note', 'Note'),
            size: 180,
            meta: {
                editor: 'text',
                filter: 'text',
                filterPlaceholder: t('entity.filter_note', 'Filter notes'),
                editable: notDraft,
            },
            cell: ({getValue}) => (
                <span className="truncate text-muted-foreground">
                    {getValue()}
                </span>
            ),
        },
    ];

    if (list.withEmojis) {
        columns.push({
            id: 'emoji',
            accessorFn: e => e.emoji ?? '',
            header: () => t('entity.emoji', 'Emoji'),
            size: 64,
            enableSorting: false,
            meta: {editor: 'emoji', align: 'center', editable: notDraft},
        });
    }
    if (list.withColors) {
        columns.push({
            id: 'color',
            accessorFn: e => e.color ?? '',
            header: () => t('tag.color', 'Color'),
            size: 90,
            enableSorting: false,
            meta: {editor: 'color', editable: notDraft},
            cell: ({getValue}) =>
                getValue() ? <ColorSwatch color={getValue()} /> : null,
        });
    }
    if (list.withTranslations) {
        for (const locale of locales) {
            const id = `${TRANSLATIONS}${locale}`;
            columns.push({
                id,
                accessorFn: e => cellValueOf(e, id),
                header: () => (
                    <span className="flex items-center gap-1">
                        <Flag locale={locale} /> {locale}
                    </span>
                ),
                size: 160,
                enableSorting: false,
                meta: {editor: 'text', editable: notDraft},
                cell: ({getValue}) => (
                    <span className="truncate">{getValue()}</span>
                ),
            });
        }
    }
    if (list.withSynonyms) {
        for (const locale of locales) {
            const id = `${SYNONYMS}${locale}`;
            columns.push({
                id,
                accessorFn: e => cellValueOf(e, id),
                header: () => (
                    <span
                        className="flex items-center gap-1"
                        title={t(
                            'entity.synonyms',
                            'Synonyms (comma separated)'
                        )}
                    >
                        ≈ <Flag locale={locale} /> {locale}
                    </span>
                ),
                size: 180,
                enableSorting: false,
                meta: {editor: 'text', editable: notDraft},
                cell: ({getValue}) => (
                    <span className="truncate text-muted-foreground">
                        {getValue()}
                    </span>
                ),
            });
        }
    }

    columns.push(
        {
            id: 'status',
            accessorKey: 'status',
            header: () => t('entity.status', 'Status'),
            size: 120,
            meta: {
                editor: 'select',
                options: statusOptions(t),
                editable: notDraft,
                getValue: e => cellValueOf(e, 'status'),
            },
            cell: ({row}) =>
                isDraft(row.original) ? null : (
                    <StatusBadge status={row.original.status} t={t} />
                ),
        },
        {
            id: 'usage',
            accessorFn: e => e.usage?.attributes,
            header: () => (
                <span
                    title={t(
                        'entity.usage_hint',
                        'Attribute values referencing this value (not sortable)'
                    )}
                >
                    {t('entity.usage', 'Usage')}
                </span>
            ),
            size: 80,
            enableSorting: false,
            meta: {align: 'right'},
            cell: ({getValue}) => (
                <span className="tabular-nums">{getValue() ?? ''}</span>
            ),
        },
        {
            id: 'updatedAt',
            accessorKey: 'updatedAt',
            header: () => t('entity.updated_at', 'Updated'),
            size: 160,
            cell: ({getValue}) => (
                <span className="truncate text-xs text-muted-foreground">
                    {formatDateTime(getValue(), 'short')}
                </span>
            ),
        },
        actionsColumn<AttributeEntity>(
            e =>
                isDraft(e) ? (
                    o.onDiscardDraft ? (
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            data-testid="entity-discard-draft"
                            aria-label={t('common.remove', 'Remove')}
                            title={t('common.remove', 'Remove')}
                            onClick={() => o.onDiscardDraft?.(e)}
                        >
                            <XIcon />
                        </Button>
                    ) : null
                ) : (
                    <span className="flex items-center gap-0.5">
                        {e.status !== AttributeEntityStatus.Approved ? (
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                data-testid="entity-approve"
                                aria-label={t('entity.approve', 'Approve')}
                                title={t('entity.approve', 'Approve')}
                                onClick={() => o.onApprove(e)}
                            >
                                <ThumbsUpIcon />
                            </Button>
                        ) : null}
                        {e.status !== AttributeEntityStatus.Rejected ? (
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                data-testid="entity-reject"
                                aria-label={t('entity.reject', 'Reject')}
                                title={t('entity.reject', 'Reject')}
                                onClick={() => o.onReject(e)}
                            >
                                <ThumbsDownIcon />
                            </Button>
                        ) : null}
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            data-testid="entity-preview"
                            aria-label={t(
                                'entity.preview',
                                'Assets using this value'
                            )}
                            title={t(
                                'entity.preview',
                                'Assets using this value'
                            )}
                            onClick={() => o.onPreview(e)}
                        >
                            <ImagesIcon />
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    data-testid="entity-actions"
                                    aria-label={t('common.more', 'More')}
                                >
                                    <MoreHorizontalIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onSelect={() => o.onEdit(e)}>
                                    <PencilIcon />{' '}
                                    {t('entity.edit_form', 'Edit…')}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => o.onDelete(e)}
                                >
                                    <Trash2Icon />{' '}
                                    {t('common.delete', 'Delete')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </span>
                ),
            140
        )
    );

    return columns;
}
