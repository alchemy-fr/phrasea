'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    EyeIcon,
    EyeOffIcon,
    GripVerticalIcon,
    RotateCcwIcon,
} from 'lucide-react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import type {Facets} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {ConfirmDialog} from '@/components/ui/confirm';
import {
    FacetPreference,
    usePreferencesStore,
} from '@/features/preferences/store';
import {cn} from '@/lib/utils/cn';

type Row = {name: string; label: string; hidden: boolean};

const EMPTY_FACETS: never[] = [];
export function FacetSettingsDialog({
    open,
    onOpenChange,
    facets,
}: ModalProps & {facets: Facets}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const prefs =
        usePreferencesStore(s => s.preferences.facets) ?? EMPTY_FACETS;
    const updatePreference = usePreferencesStore(s => s.updatePreference);

    const initial = useMemo<Row[]>(() => {
        const names = new Set([
            ...Object.keys(facets),
            ...prefs.map(p => p.name),
        ]);
        const rows = [...names].map(name => {
            const pref = prefs.find(p => p.name === name);

            return {
                name,
                label: facets[name]?.meta.displayName ?? name,
                hidden: !!pref?.hidden,
                order: pref?.order ?? 999999,
            };
        });
        rows.sort(
            (a, b) => a.order - b.order || a.label.localeCompare(b.label)
        );

        return rows;
    }, [facets, prefs]);

    const [rows, setRows] = useState<Row[]>(initial);
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}})
    );

    const onDragEnd = (e: DragEndEvent) => {
        const {active, over} = e;
        if (over && active.id !== over.id) {
            setRows(prev =>
                arrayMove(
                    prev,
                    prev.findIndex(r => r.name === active.id),
                    prev.findIndex(r => r.name === over.id)
                )
            );
        }
    };

    const save = async () => {
        const next: FacetPreference[] = rows.map((r, i) =>
            r.hidden ? {name: r.name, hidden: true} : {name: r.name, order: i}
        );
        await updatePreference('facets', next);
        onOpenChange(false);
    };

    const visibleRows = rows.filter(r => !r.hidden);
    const hiddenRows = rows.filter(r => r.hidden);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t('facets.settings', 'Facet settings')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'facets.settings_help',
                            'Drag to reorder facets, toggle their visibility.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody className="space-y-3">
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={onDragEnd}
                    >
                        <SortableContext
                            items={visibleRows.map(r => r.name)}
                            strategy={verticalListSortingStrategy}
                        >
                            <ul className="space-y-1">
                                {visibleRows.map(r => (
                                    <FacetRow
                                        key={r.name}
                                        row={r}
                                        onToggle={() =>
                                            setRows(prev =>
                                                prev.map(x =>
                                                    x.name === r.name
                                                        ? {...x, hidden: true}
                                                        : x
                                                )
                                            )
                                        }
                                    />
                                ))}
                            </ul>
                        </SortableContext>
                    </DndContext>
                    {hiddenRows.length > 0 ? (
                        <div>
                            <h4 className="mb-1 text-xs font-semibold text-muted-foreground uppercase">
                                {t('facets.hidden_section', 'Hidden')}
                            </h4>
                            <ul className="space-y-1">
                                {hiddenRows.map(r => (
                                    <li
                                        key={r.name}
                                        className="flex items-center gap-2 rounded-md border px-2 py-1 text-sm text-muted-foreground"
                                    >
                                        <span className="flex-1 truncate">
                                            {r.label}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="icon-xs"
                                            onClick={() =>
                                                setRows(prev =>
                                                    prev.map(x =>
                                                        x.name === r.name
                                                            ? {
                                                                  ...x,
                                                                  hidden: false,
                                                              }
                                                            : x
                                                    )
                                                )
                                            }
                                        >
                                            <EyeIcon />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </DialogBody>
                <DialogFooter className="sm:justify-between">
                    <Button
                        variant="ghost"
                        onClick={() =>
                            openModal(ConfirmDialog, {
                                title: t(
                                    'facets.reset.title',
                                    'Reset facets to default?'
                                ),
                                onConfirm: async () => {
                                    await updatePreference('facets', []);
                                    onOpenChange(false);
                                },
                            })
                        }
                    >
                        <RotateCcwIcon />{' '}
                        {t('facets.reset', 'Reset to default')}
                    </Button>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('common.cancel', 'Cancel')}
                        </Button>
                        <Button onClick={save}>
                            {t('common.save', 'Save')}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function FacetRow({row, onToggle}: {row: Row; onToggle: () => void}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id: row.name});

    return (
        <li
            ref={setNodeRef}
            style={{transform: CSS.Transform.toString(transform), transition}}
            className={cn(
                'flex items-center gap-2 rounded-md border bg-background px-2 py-1 text-sm',
                isDragging && 'z-10 shadow-md'
            )}
        >
            <button
                type="button"
                className="cursor-grab text-muted-foreground"
                {...attributes}
                {...listeners}
                aria-label="Drag"
            >
                <GripVerticalIcon className="size-4" />
            </button>
            <span className="flex-1 truncate">{row.label}</span>
            <Button variant="ghost" size="icon-xs" onClick={onToggle}>
                <EyeOffIcon />
            </Button>
        </li>
    );
}
