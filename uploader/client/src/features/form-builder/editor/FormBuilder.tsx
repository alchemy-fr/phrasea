'use client';

import {ReactNode, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragMoveEvent,
} from '@dnd-kit/core';
import {arrayMove, sortableKeyboardCoordinates} from '@dnd-kit/sortable';
import {
    overlayRow,
    SortableOverlay,
    sortableMeasuring,
} from '@alchemy/next-ui/ui/sortable';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {
    BUILTINS,
    CUSTOM_TYPES,
    fieldFromAttribute,
    fieldFromBuiltin,
    fieldLabel,
    isFieldCustomized,
    isInputField,
    localized,
    newCustomField,
    newLayout,
} from '../definition';
import type {
    BuilderField,
    BuiltinKey,
    DataboxField,
    FieldOptionsLoader,
    FieldType,
    FormDefinition,
} from '../types';
import {canvasCollision, canvasDropOf, paletteIdOf} from './dnd';
import {FieldCard, FormCanvas, type FieldIssues} from './FormCanvas';
import {FieldInspector} from './FieldInspector';
import {useSyncedColumns} from './useSyncedColumns';
import {
    FieldPalette,
    PaletteDragPreview,
    type PaletteGroup,
    type PaletteItem,
} from './FieldPalette';

type Props = {
    definition: FormDefinition;
    onChange: (definition: FormDefinition) => void;
    /** The attributes of the workspace; undefined while loading or without workspace */
    databoxFields?: DataboxField[];
    /** Shown in the Databox group of the palette instead of its attributes */
    databoxStatus?: ReactNode;
    /** The languages the texts of the form are written in */
    locales: string[];
    loadOptions: FieldOptionsLoader;
    issues: FieldIssues;
    disabled?: boolean;
};

/**
 * The columns, see {@link useSyncedColumns}: below the top bar of the app,
 * above the save bar of the editor.
 */
const syncedColumn =
    'lg:sticky lg:top-4 lg:max-h-[calc(100dvh-9rem)] lg:self-start lg:overflow-hidden';

const attributeItemId = (slug: string) => `attribute:${slug}`;
const builtinItemId = (key: string) => `builtin:${key}`;
const customItemId = (type: string) => `custom:${type}`;
const layoutItemId = (kind: string) => `layout:${kind}`;

function usedKeys(definition: FormDefinition): Set<string> {
    const keys = new Set<string>();
    for (const field of definition.fields) {
        if (isInputField(field)) {
            keys.add(field.key);
            // A key may carry the locale of its value ("title:fr")
            keys.add(field.key.replace(/:[A-Za-z_-]{2,5}$/, ''));
        }
    }

    return keys;
}

/**
 * The form builder: a palette of fields (the attributes of the Databox
 * workspace, the built-in values, custom fields, layout), the form they are
 * dragged into, and the settings of the selected field.
 */
export function FormBuilder({
    definition,
    onChange,
    databoxFields,
    databoxStatus,
    locales,
    loadOptions,
    issues,
    disabled,
}: Props) {
    const {t, i18n} = useTranslation();
    const confirm = useConfirm();
    const [selectedId, setSelectedId] = useState<string>();
    /** Where the palette item being dragged would land */
    const [dropIndex, setDropIndex] = useState<number>();
    const gridRef = useSyncedColumns();
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const used = useMemo(() => usedKeys(definition), [definition]);
    const fields = definition.fields;
    const selected = fields.find(f => f.id === selectedId);

    const groups: PaletteGroup[] = useMemo(() => {
        const lang = i18n.language;

        return [
            {
                id: 'databox',
                title: t('builder.palette.databox', 'Databox attributes'),
                status: databoxStatus,
                items: (databoxFields ?? []).map(f => ({
                    id: attributeItemId(f.slug),
                    label: localized(f.label, lang, f.displayName || f.name),
                    hint: t(`builder.type.${f.type}`, f.type),
                    used: used.has(f.slug),
                })),
            },
            {
                id: 'builtin',
                title: t('builder.palette.builtin', 'Databox values'),
                items: BUILTINS.filter(
                    b => b.type !== 'tag' || !!definition.workspaceId
                ).map(b => ({
                    id: builtinItemId(b.key),
                    label: t(
                        `builder.builtin.${b.key}`,
                        localized(b.label, lang)
                    ),
                    hint: b.key,
                    used: used.has(b.key),
                })),
            },
            {
                id: 'custom',
                title: t('builder.palette.custom', 'Custom fields'),
                items: CUSTOM_TYPES.map(type => ({
                    id: customItemId(type),
                    label: t(`builder.type.${type}`, type),
                })),
            },
            {
                id: 'layout',
                title: t('builder.palette.layout', 'Layout'),
                items: [
                    {
                        id: layoutItemId('section'),
                        label: t('builder.layout.section', 'Section'),
                    },
                    {
                        id: layoutItemId('text'),
                        label: t('builder.layout.text', 'Text'),
                    },
                ],
            },
        ];
    }, [
        t,
        i18n.language,
        databoxFields,
        databoxStatus,
        used,
        definition.workspaceId,
    ]);

    const paletteItems = useMemo(
        () =>
            new Map<string, PaletteItem>(
                groups.flatMap(g => g.items.map(i => [i.id, i]))
            ),
        [groups]
    );

    const create = (itemId: string): BuilderField | undefined => {
        const [kind, name] = [
            itemId.slice(0, itemId.indexOf(':')),
            itemId.slice(itemId.indexOf(':') + 1),
        ];
        switch (kind) {
            case 'attribute': {
                const attribute = databoxFields?.find(f => f.slug === name);

                return attribute ? fieldFromAttribute(attribute) : undefined;
            }
            case 'builtin':
                return fieldFromBuiltin(name as BuiltinKey);
            case 'custom':
                return newCustomField(name as FieldType, [...used]);
            case 'layout':
                return newLayout(name as 'section' | 'text');
        }

        return undefined;
    };

    const insert = (itemId: string, index = fields.length) => {
        const field = create(itemId);
        if (!field) {
            return;
        }
        const next = [...fields];
        next.splice(index, 0, field);
        onChange({...definition, fields: next});
        setSelectedId(field.id);
    };

    const update = (field: BuilderField) =>
        onChange({
            ...definition,
            fields: fields.map(f => (f.id === field.id ? field : f)),
        });

    /** Asks first when the settings of the field would be lost */
    const remove = async (id: string) => {
        const field = fields.find(f => f.id === id);
        if (
            field &&
            isFieldCustomized(field, databoxFields) &&
            !(await confirm({
                title: t('builder.field.remove_title', 'Remove the field'),
                description: t(
                    'builder.field.remove_confirm',
                    'The settings of "{{label}}" will be lost.',
                    {label: fieldLabel(field, i18n.language)}
                ),
                confirmLabel: t('builder.field.remove', 'Remove'),
                destructive: true,
            }))
        ) {
            return;
        }
        onChange({...definition, fields: fields.filter(f => f.id !== id)});
        if (selectedId === id) {
            setSelectedId(undefined);
        }
    };

    const onDragMove = ({active, collisions}: DragMoveEvent) => {
        if (paletteIdOf(active.id)) {
            setDropIndex(canvasDropOf(collisions)?.index);
        }
    };

    const onDragEnd = ({active, over, collisions}: DragEndEvent) => {
        setDropIndex(undefined);
        const paletteId = paletteIdOf(active.id);
        if (paletteId) {
            const drop = canvasDropOf(collisions);
            if (drop) {
                insert(paletteId, drop.index);
            }

            return;
        }
        const from = fields.findIndex(f => f.id === active.id);
        const to = over ? fields.findIndex(f => f.id === over.id) : -1;
        if (from >= 0 && to >= 0 && from !== to) {
            onChange({...definition, fields: arrayMove(fields, from, to)});
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={canvasCollision}
            measuring={sortableMeasuring}
            onDragMove={onDragMove}
            onDragEnd={onDragEnd}
            onDragCancel={() => setDropIndex(undefined)}
        >
            <div
                ref={gridRef}
                className="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)_20rem]"
            >
                <div data-synced-column className={syncedColumn}>
                    <FieldPalette
                        groups={groups}
                        onAdd={id => insert(id)}
                        disabled={disabled}
                    />
                </div>
                <div data-synced-column className={syncedColumn}>
                    <FormCanvas
                        fields={fields}
                        selectedId={selectedId}
                        onSelect={setSelectedId}
                        onRemove={id => void remove(id)}
                        dropIndex={dropIndex}
                        loadOptions={loadOptions}
                        issues={issues}
                        disabled={disabled}
                        // Drops anywhere in the column
                        className="lg:min-h-[calc(100dvh-9rem)]"
                    />
                </div>
                <div data-synced-column className={syncedColumn}>
                    <aside className="rounded-lg border p-3">
                        {selected ? (
                            <FieldInspector
                                key={selected.id}
                                field={selected}
                                onChange={update}
                                locales={locales}
                                loadOptions={loadOptions}
                                issue={issues[selected.id]}
                                disabled={disabled}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'builder.inspector.empty',
                                    'Select a field of the form to set it.'
                                )}
                            </p>
                        )}
                    </aside>
                </div>
            </div>
            <SortableOverlay>
                {id => {
                    const paletteId = paletteIdOf(id);
                    if (paletteId) {
                        const item = paletteItems.get(paletteId);

                        return item ? <PaletteDragPreview item={item} /> : null;
                    }
                    const field = fields.find(f => f.id === id);

                    return field ? (
                        <FieldCard field={field} row={overlayRow} />
                    ) : null;
                }}
            </SortableOverlay>
        </DndContext>
    );
}
