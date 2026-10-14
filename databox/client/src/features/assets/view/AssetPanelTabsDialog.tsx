'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {GripVerticalIcon, RotateCcwIcon} from 'lucide-react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Button} from '@/components/ui/button';
import {useConfirm} from '@/components/ui/confirm';
import {usePreferencesStore} from '@/features/preferences/store';
import {
    overlayRow,
    SortableOverlay,
    SortableRow,
    sortableMeasuring,
    useSortableRow,
} from '@/components/ui/sortable';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {mergeOrder} from '@/lib/utils/order';
import {cn} from '@/lib/utils/cn';

export type ReorderableTab = {id: string; title: string; icon: ReactNode};

/**
 * Reorders the tabs of the asset side panel. Only the tabs shown for the
 * current asset are listed: the others (integrations of another file type,
 * tabs needing other rights) keep their saved place.
 */
export function AssetPanelTabsDialog({
    open,
    onOpenChange,
    tabs,
}: ModalProps & {tabs: ReorderableTab[]}) {
    const {t} = useTranslation();
    const confirm = useConfirm();
    const saved = usePreferencesStore(s => s.preferences.assetPanelTabs);
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const [rows, setRows] = useState(tabs);
    const {dirty} = useDirtyState(rows.map(r => r.id));
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const onDragEnd = ({active, over}: DragEndEvent) => {
        if (over && active.id !== over.id) {
            setRows(prev =>
                arrayMove(
                    prev,
                    prev.findIndex(r => r.id === active.id),
                    prev.findIndex(r => r.id === over.id)
                )
            );
        }
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('asset.panel.reorder_tabs', 'Reorder tabs')}
            description={t(
                'asset.panel.reorder_tabs_help',
                'Drag the tabs to change their order.'
            )}
            footerStart={
                <Button
                    variant="ghost"
                    // Not returned: the confirm dialog shows the request
                    // spinner, this button must not spin while it is open
                    onClick={() => {
                        void confirm({
                            title: t(
                                'asset.panel.reset_tabs',
                                'Reset the order of the tabs?'
                            ),
                            onConfirm: () =>
                                updatePreference('assetPanelTabs', []),
                        }).then(done => {
                            if (done) {
                                onOpenChange(false);
                            }
                        });
                    }}
                >
                    <RotateCcwIcon /> {t('facets.reset', 'Reset to default')}
                </Button>
            }
            dirty={dirty}
            onSubmit={() =>
                updatePreference(
                    'assetPanelTabs',
                    mergeOrder(
                        saved,
                        rows.map(r => r.id)
                    )
                )
            }
        >
            <DndContext
                measuring={sortableMeasuring}
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={onDragEnd}
            >
                <SortableContext
                    items={rows.map(r => r.id)}
                    strategy={verticalListSortingStrategy}
                >
                    <ul
                        className="space-y-1"
                        data-testid="asset-panel-tabs-order"
                    >
                        {rows.map(r => (
                            <TabRow key={r.id} tab={r} />
                        ))}
                    </ul>
                </SortableContext>
                <SortableOverlay>
                    {id => {
                        const row = rows.find(r => r.id === id);

                        return row ? (
                            <TabRowView tab={row} drag={overlayRow} />
                        ) : null;
                    }}
                </SortableOverlay>
            </DndContext>
        </FormDialog>
    );
}

function TabRow({tab}: {tab: ReorderableTab}) {
    const drag = useSortableRow(tab.id);

    return <TabRowView tab={tab} drag={drag} />;
}

/** A tab of the list, also rendered as the copy following the pointer */
function TabRowView({tab, drag}: {tab: ReorderableTab; drag: SortableRow}) {
    const {t} = useTranslation();

    return (
        <li
            ref={drag.nodeRef}
            style={drag.style}
            data-tab-id={tab.id}
            className={cn(
                'flex list-none items-center gap-2 rounded-md border bg-background px-2 py-1.5 text-sm [&_svg]:size-4',
                drag.className
            )}
        >
            <button
                type="button"
                className="cursor-grab text-muted-foreground"
                {...drag.handle}
                aria-label={t('asset.panel.move_tab', 'Move {{name}}', {
                    name: tab.title,
                })}
            >
                <GripVerticalIcon />
            </button>
            <span className="text-muted-foreground">{tab.icon}</span>
            <span className="flex-1 truncate">{tab.title}</span>
        </li>
    );
}
