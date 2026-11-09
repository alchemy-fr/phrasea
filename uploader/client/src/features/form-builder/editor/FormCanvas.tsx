'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useDroppable} from '@dnd-kit/core';
import {SortableContext, verticalListSortingStrategy} from '@dnd-kit/sortable';
import {
    AlertTriangleIcon,
    EyeOffIcon,
    GripVerticalIcon,
    HeadingIcon,
    TextIcon,
    Trash2Icon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Badge} from '@alchemy/next-ui/ui/misc';
import {useSortableRow, type SortableRow} from '@alchemy/next-ui/ui/sortable';
import {cn} from '@alchemy/next-ui/utils/cn';
import {fieldLabel} from '../definition';
import {Field} from '../render/FormRenderer';
import {useBuilderForm} from '../render/useBuilderForm';
import type {BuilderField, FieldOptionsLoader, FormDefinition} from '../types';
import {CANVAS_ZONE} from './dnd';

export type FieldIssues = Record<string, string>;

export function FormCanvas({
    fields,
    selectedId,
    onSelect,
    onRemove,
    dropIndex,
    loadOptions,
    issues,
    disabled,
    className,
}: {
    fields: BuilderField[];
    selectedId?: string;
    onSelect: (id: string) => void;
    onRemove: (id: string) => void;
    /** Where the palette item being dragged would land */
    dropIndex?: number;
    /** For the preview of the selected field */
    loadOptions: FieldOptionsLoader;
    /** Why a field cannot be saved or will not reach Databox, by field id */
    issues: FieldIssues;
    disabled?: boolean;
    className?: string;
}) {
    const {t} = useTranslation();
    const {setNodeRef, isOver} = useDroppable({id: CANVAS_ZONE});

    return (
        <div
            ref={setNodeRef}
            data-testid="builder-canvas"
            className={cn(
                'min-h-64 rounded-lg border border-dashed p-3 transition-colors',
                isOver && 'border-primary bg-primary/5',
                className
            )}
        >
            <SortableContext
                items={fields.map(f => f.id)}
                strategy={verticalListSortingStrategy}
            >
                <ul className="flex flex-col gap-1.5">
                    {fields.map((field, index) => (
                        <CanvasRow
                            key={field.id}
                            field={field}
                            selected={field.id === selectedId}
                            issue={issues[field.id]}
                            onSelect={() => onSelect(field.id)}
                            onRemove={() => onRemove(field.id)}
                            dropMark={
                                dropIndex === index
                                    ? 'before'
                                    : dropIndex === fields.length &&
                                        index === fields.length - 1
                                      ? 'after'
                                      : undefined
                            }
                            loadOptions={loadOptions}
                            disabled={disabled}
                        />
                    ))}
                </ul>
            </SortableContext>
            {fields.length === 0 ? (
                <p className="py-16 text-center text-sm text-muted-foreground">
                    {t(
                        'builder.canvas.empty',
                        'Drag fields here from the list, or add them with +.'
                    )}
                </p>
            ) : null}
        </div>
    );
}

function CanvasRow({
    field,
    selected,
    issue,
    onSelect,
    onRemove,
    dropMark,
    loadOptions,
    disabled,
}: {
    field: BuilderField;
    selected: boolean;
    issue?: string;
    onSelect: () => void;
    onRemove: () => void;
    /** The palette item being dragged would land before or after the row */
    dropMark?: 'before' | 'after';
    loadOptions: FieldOptionsLoader;
    disabled?: boolean;
}) {
    const row = useSortableRow(field.id, disabled);

    return (
        <li
            ref={row.nodeRef}
            style={row.style}
            className={cn('relative', row.className)}
            data-testid="builder-canvas-field"
            data-key={field.source === 'layout' ? undefined : field.key}
        >
            {dropMark ? (
                // In the gap between the rows: the fields do not move
                <span
                    aria-hidden
                    data-testid="builder-drop-mark"
                    className={cn(
                        'pointer-events-none absolute inset-x-0 h-0.5 rounded-full bg-primary',
                        dropMark === 'before' ? '-top-1' : '-bottom-1'
                    )}
                />
            ) : null}
            <FieldCard
                field={field}
                row={row}
                selected={selected}
                issue={issue}
                onSelect={onSelect}
                onRemove={onRemove}
                disabled={disabled}
            />
            {selected ? (
                <FieldPreview
                    // A new type or default value starts from it again
                    key={JSON.stringify([
                        field.source !== 'layout' && [
                            field.type,
                            field.multiple,
                            field.defaultValue,
                        ],
                    ])}
                    field={field}
                    loadOptions={loadOptions}
                />
            ) : null}
        </li>
    );
}

/** The selected field as users will see it */
function FieldPreview({
    field,
    loadOptions,
}: {
    field: BuilderField;
    loadOptions: FieldOptionsLoader;
}) {
    const {t} = useTranslation();
    const definition = useMemo<FormDefinition>(
        () => ({version: 1, fields: [field]}),
        [field]
    );
    const form = useBuilderForm(definition);
    const key = field.source === 'layout' ? undefined : field.key;

    return (
        <div
            data-testid="builder-field-preview"
            className="mx-2 rounded-b-md border border-t-0 bg-muted/40 px-3 pt-3 pb-px"
        >
            {field.source !== 'layout' && field.hidden ? (
                <p className="mb-3 text-sm text-muted-foreground italic">
                    {t(
                        'builder.preview.hidden',
                        'Not shown: its default value is always sent.'
                    )}
                </p>
            ) : (
                <Field
                    field={field}
                    value={key === undefined ? undefined : form.values[key]}
                    onChange={v =>
                        key !== undefined &&
                        form.setValues({...form.values, [key]: v})
                    }
                    loadOptions={loadOptions}
                />
            )}
        </div>
    );
}

export function FieldCard({
    field,
    row,
    selected,
    issue,
    onSelect,
    onRemove,
    disabled,
}: {
    field: BuilderField;
    row: SortableRow;
    selected?: boolean;
    issue?: string;
    onSelect?: () => void;
    onRemove?: () => void;
    disabled?: boolean;
}) {
    const {t, i18n} = useTranslation();
    const label = fieldLabel(field, i18n.language);

    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-md border bg-background px-2 py-1.5 text-sm',
                selected && 'border-primary ring-1 ring-primary',
                row.className
            )}
        >
            <span
                {...row.handle}
                className="cursor-grab text-muted-foreground"
                aria-label={t('builder.drag', 'Drag')}
            >
                <GripVerticalIcon className="size-4" />
            </span>
            <button
                type="button"
                onClick={onSelect}
                className="flex min-w-0 flex-1 items-center gap-2 text-left"
            >
                {field.source === 'layout' ? (
                    <>
                        {field.kind === 'section' ? (
                            <HeadingIcon className="size-4 text-muted-foreground" />
                        ) : (
                            <TextIcon className="size-4 text-muted-foreground" />
                        )}
                        <span
                            className={cn(
                                'truncate',
                                field.kind === 'section' && 'font-semibold',
                                !label && 'text-muted-foreground italic'
                            )}
                        >
                            {label ||
                                (field.kind === 'section'
                                    ? t('builder.layout.section', 'Section')
                                    : t('builder.layout.text', 'Text'))}
                        </span>
                    </>
                ) : (
                    <>
                        <span className="truncate">
                            {label}
                            {field.required ? (
                                <span aria-hidden className="text-destructive">
                                    *
                                </span>
                            ) : null}
                        </span>
                        <Badge
                            variant="outline"
                            className="font-mono text-[10px]"
                        >
                            {field.key}
                        </Badge>
                        <Badge variant="muted" className="text-[10px]">
                            {t(`builder.type.${field.type}`, field.type)}
                            {field.multiple ? ' []' : ''}
                        </Badge>
                        {field.source === 'attribute' ? (
                            <Badge variant="secondary" className="text-[10px]">
                                Databox
                            </Badge>
                        ) : null}
                        {field.hidden ? (
                            <EyeOffIcon
                                className="size-4 text-muted-foreground"
                                aria-label={t('builder.field.hidden', 'Hidden')}
                            />
                        ) : null}
                    </>
                )}
                {issue ? (
                    <span title={issue} className="ml-auto text-warning">
                        <AlertTriangleIcon
                            className="size-4"
                            aria-label={issue}
                        />
                    </span>
                ) : null}
            </button>
            {onRemove ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    disabled={disabled}
                    onClick={onRemove}
                    aria-label={t('builder.field.remove', 'Remove')}
                >
                    <Trash2Icon />
                </Button>
            ) : null}
        </div>
    );
}
