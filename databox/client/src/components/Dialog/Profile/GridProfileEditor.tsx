import React, {useMemo, useState} from 'react';
import {
    DndContext,
    DragEndEvent,
    DragOverlay,
    DragStartEvent,
    MouseSensor,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    Box,
    Chip,
    Divider,
    FormControlLabel,
    IconButton,
    Paper,
    Stack,
    Switch,
    ToggleButton,
    ToggleButtonGroup,
    Typography,
} from '@mui/material';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Button from '@mui/material/Button';
import CloseIcon from '@mui/icons-material/Close';
import ImageIcon from '@mui/icons-material/Image';
import {useTranslation} from 'react-i18next';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {logError} from '@alchemy/core';
import {
    DisplayProfile,
    GridAnchor,
    GridRegion,
    ProfileItem,
    ProfileItemSection,
    ProfileItemVariant,
} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {
    attributeDefinitionToItem,
    hasDefinitionInItems,
    useProfileStore,
} from '../../../store/profileStore.ts';
import {
    AttributeDefinitionsIndex,
    useAttributeDefinitionStore,
    useIndexById,
} from '../../../store/attributeDefinitionStore.ts';
import {putProfileItem} from '../../../api/profile.ts';
import {getItemLabel} from './Transfer/Item.tsx';

type Props = {
    id: string;
    data: DisplayProfile;
} & DialogTabProps;

const OVER_GRID: GridAnchor[][] = [
    ['tl', 'tc', 'tr'],
    ['ml', 'cc', 'mr'],
    ['bl', 'bc', 'br'],
];
const BAND: GridAnchor[] = ['l', 'c', 'r'];

const DRAG_PALETTE = 'palette:';
const DRAG_ITEM = 'item:';
const DROP_CELL = 'cell:';

const cellId = (region: GridRegion, anchor: GridAnchor) =>
    `${DROP_CELL}${region}:${anchor}`;

function parseCell(
    id: string
): {region: GridRegion; anchor: GridAnchor} | null {
    if (!id.startsWith(DROP_CELL)) return null;
    const [region, anchor] = id.slice(DROP_CELL.length).split(':');
    return {region: region as GridRegion, anchor: anchor as GridAnchor};
}

export default function GridProfileEditor({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const load = useAttributeDefinitionStore(state => state.load);
    React.useEffect(() => {
        load();
    }, [load]);

    const {allDefinitions, loaded} = useAttributeDefinitionStore(state => ({
        loaded: state.loaded,
        allDefinitions: [...state.builtIn, ...state.definitions],
    }));
    const definitionsIndex = useIndexById(true);

    const addToList = useProfileStore(s => s.addToList);
    const removeFromProfile = useProfileStore(s => s.removeFromProfile);
    const updateProfileItem = useProfileStore(s => s.updateProfileItem);

    const [selectedId, setSelectedId] = useState<string | undefined>();
    const [activeDrag, setActiveDrag] = useState<string | null>(null);

    const gridItems = useMemo(
        () =>
            (data.items ?? []).filter(
                i => i.section === ProfileItemSection.Grid
            ),
        [data.items]
    );

    const availableDefinitions = useMemo(
        () =>
            allDefinitions.filter(d => !hasDefinitionInItems(gridItems, d.id)),
        [allDefinitions, gridItems]
    );

    const anchorItems = (region: GridRegion, anchor: GridAnchor) =>
        gridItems
            .filter(
                i =>
                    i.placement?.region === region &&
                    i.placement?.anchor === anchor
            )
            .sort(
                (a, b) => (a.placement?.order ?? 0) - (b.placement?.order ?? 0)
            );

    const sensors = useSensors(
        useSensor(MouseSensor, {activationConstraint: {distance: 8}}),
        useSensor(TouchSensor)
    );

    const persistPatch = async (
        item: ProfileItem,
        changes: Partial<ProfileItem>
    ) => {
        const next = {...item, ...changes};
        updateProfileItem(data.id, next);
        try {
            await putProfileItem(data.id, item.id, next);
        } catch (e) {
            logError(e);
        }
    };

    const handleDragStart = ({active}: DragStartEvent) => {
        setActiveDrag(active.id as string);
    };

    const handleDragEnd = ({active, over}: DragEndEvent) => {
        setActiveDrag(null);
        if (!over) return;

        const target = parseCell(over.id as string);
        if (!target) return;

        const order = anchorItems(target.region, target.anchor).length;
        const placement = {...target, order};
        const activeId = active.id as string;

        if (activeId.startsWith(DRAG_PALETTE)) {
            const defId = activeId.slice(DRAG_PALETTE.length);
            const def = definitionsIndex[defId];
            if (!def) return;
            const item: ProfileItem = {
                ...attributeDefinitionToItem(def),
                section: ProfileItemSection.Grid,
                placement,
                variant: 'chip',
            };
            addToList(data.id, [item]);
            return;
        }

        if (activeId.startsWith(DRAG_ITEM)) {
            const itemId = activeId.slice(DRAG_ITEM.length);
            const item = gridItems.find(i => i.id === itemId);
            if (!item) return;
            if (
                item.placement?.region === target.region &&
                item.placement?.anchor === target.anchor
            ) {
                return; // dropped on same cell
            }
            persistPatch(item, {placement});
        }
    };

    const selected = gridItems.find(i => i.id === selectedId);

    if (!loaded || !data.items) {
        return <FullPageLoader />;
    }

    const renderCell = (region: GridRegion, anchor: GridAnchor) => (
        <AnchorCell key={anchor} region={region} anchor={anchor}>
            {anchorItems(region, anchor).map(item => (
                <PlacedChip
                    key={item.id}
                    item={item}
                    label={getItemLabel(item, definitionsIndex)}
                    selected={item.id === selectedId}
                    onSelect={() => setSelectedId(item.id)}
                    onRemove={() => {
                        if (item.id === selectedId) setSelectedId(undefined);
                        removeFromProfile(data.id, [item.id]);
                    }}
                />
            ))}
        </AnchorCell>
    );

    return (
        <>
            <DialogContent sx={{minHeight}}>
                <DndContext
                    sensors={sensors}
                    onDragStart={handleDragStart}
                    onDragEnd={handleDragEnd}
                >
                    <Stack direction="row" spacing={2} sx={{flexWrap: 'wrap'}}>
                        {/* Palette */}
                        <Paper
                            variant="outlined"
                            sx={{width: 220, p: 1, alignSelf: 'flex-start'}}
                        >
                            <Typography
                                variant="subtitle2"
                                sx={{px: 1, py: 0.5}}
                            >
                                {t(
                                    'grid_editor.palette.title',
                                    'Available fields'
                                )}
                            </Typography>
                            <Typography
                                variant="caption"
                                color="text.secondary"
                                sx={{px: 1}}
                            >
                                {t(
                                    'grid_editor.palette.hint',
                                    'Drag a field onto the card'
                                )}
                            </Typography>
                            <Box
                                sx={{
                                    mt: 1,
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 0.5,
                                    maxHeight: 340,
                                    overflow: 'auto',
                                }}
                            >
                                {availableDefinitions.map(def => (
                                    <PaletteField
                                        key={def.id}
                                        id={def.id}
                                        label={
                                            def.displayName ??
                                            def.name ??
                                            def.id
                                        }
                                    />
                                ))}
                                {availableDefinitions.length === 0 && (
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                        sx={{p: 1}}
                                    >
                                        {t(
                                            'grid_editor.palette.empty',
                                            'All fields are placed'
                                        )}
                                    </Typography>
                                )}
                            </Box>
                        </Paper>

                        {/* Card canvas */}
                        <Box sx={{flexGrow: 1, minWidth: 260}}>
                            <Box sx={{maxWidth: 320, mx: 'auto'}}>
                                <BandZone>
                                    {BAND.map(a => renderCell('above', a))}
                                </BandZone>

                                <Box
                                    sx={{
                                        position: 'relative',
                                        aspectRatio: '1 / 1',
                                        bgcolor: 'action.hover',
                                        borderRadius: 1,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <ImageIcon
                                        sx={{
                                            fontSize: 64,
                                            color: 'action.disabled',
                                        }}
                                    />
                                    <Box
                                        sx={{
                                            position: 'absolute',
                                            inset: 0,
                                            display: 'grid',
                                            gridTemplateColumns:
                                                'repeat(3,1fr)',
                                            gridTemplateRows: 'repeat(3,1fr)',
                                            gap: 0.5,
                                            p: 0.5,
                                        }}
                                    >
                                        {OVER_GRID.flatMap(row =>
                                            row.map(a => renderCell('over', a))
                                        )}
                                    </Box>
                                </Box>

                                <BandZone>
                                    {BAND.map(a => renderCell('below', a))}
                                </BandZone>
                            </Box>
                        </Box>

                        {/* Config panel */}
                        {selected && (
                            <ConfigPanel
                                key={selected.id}
                                item={selected}
                                label={getItemLabel(selected, definitionsIndex)}
                                onChange={changes =>
                                    persistPatch(selected, changes)
                                }
                                onClose={() => setSelectedId(undefined)}
                            />
                        )}
                    </Stack>

                    <DragOverlay>
                        {activeDrag ? (
                            <Chip
                                size="small"
                                label={dragOverlayLabel(
                                    activeDrag,
                                    gridItems,
                                    definitionsIndex
                                )}
                            />
                        ) : null}
                    </DragOverlay>
                </DndContext>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>{t('dialog.close', 'Close')}</Button>
            </DialogActions>
        </>
    );
}

function dragOverlayLabel(
    activeId: string,
    gridItems: ProfileItem[],
    definitionsIndex: AttributeDefinitionsIndex
): string {
    if (activeId.startsWith(DRAG_PALETTE)) {
        const def = definitionsIndex[activeId.slice(DRAG_PALETTE.length)];
        return def?.displayName ?? def?.name ?? '';
    }
    const item = gridItems.find(i => i.id === activeId.slice(DRAG_ITEM.length));
    return item ? getItemLabel(item, definitionsIndex) : '';
}

function PaletteField({id, label}: {id: string; label: string}) {
    const {attributes, listeners, setNodeRef, isDragging} = useDraggable({
        id: `${DRAG_PALETTE}${id}`,
    });
    return (
        <Chip
            ref={setNodeRef}
            size="small"
            label={label}
            variant="outlined"
            sx={{cursor: 'grab', opacity: isDragging ? 0.4 : 1}}
            {...listeners}
            {...attributes}
        />
    );
}

function BandZone({children}: {children: React.ReactNode}) {
    return (
        <Box
            sx={{
                display: 'grid',
                gridTemplateColumns: 'repeat(3,1fr)',
                gap: 0.5,
                my: 0.5,
                minHeight: 34,
            }}
        >
            {children}
        </Box>
    );
}

function AnchorCell({
    region,
    anchor,
    children,
}: {
    region: GridRegion;
    anchor: GridAnchor;
    children: React.ReactNode;
}) {
    const {setNodeRef, isOver} = useDroppable({id: cellId(region, anchor)});
    return (
        <Box
            ref={setNodeRef}
            sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 0.25,
                minHeight: 28,
                minWidth: 0,
                borderRadius: 0.5,
                border: '1px dashed',
                borderColor: isOver ? 'primary.main' : 'divider',
                bgcolor: isOver ? 'action.selected' : 'transparent',
                p: 0.25,
                alignItems:
                    anchor === 'r' || anchor.endsWith('r')
                        ? 'flex-end'
                        : anchor === 'c' || anchor.endsWith('c')
                          ? 'center'
                          : 'flex-start',
            }}
        >
            {children}
        </Box>
    );
}

function PlacedChip({
    item,
    label,
    selected,
    onSelect,
    onRemove,
}: {
    item: ProfileItem;
    label: string;
    selected: boolean;
    onSelect: () => void;
    onRemove: () => void;
}) {
    const {attributes, listeners, setNodeRef, isDragging} = useDraggable({
        id: `${DRAG_ITEM}${item.id}`,
    });
    return (
        <Chip
            ref={setNodeRef}
            size="small"
            label={label}
            color={selected ? 'primary' : 'default'}
            variant={item.variant === 'text' ? 'outlined' : 'filled'}
            onClick={onSelect}
            onDelete={onRemove}
            deleteIcon={<CloseIcon />}
            sx={{
                cursor: 'grab',
                maxWidth: '100%',
                opacity: isDragging ? 0.4 : 1,
            }}
            {...listeners}
            {...attributes}
        />
    );
}

function ConfigPanel({
    item,
    label,
    onChange,
    onClose,
}: {
    item: ProfileItem;
    label: string;
    onChange: (changes: Partial<ProfileItem>) => void;
    onClose: () => void;
}) {
    const {t} = useTranslation();
    return (
        <Paper
            variant="outlined"
            sx={{width: 240, p: 2, alignSelf: 'flex-start'}}
        >
            <Stack
                direction="row"
                sx={{alignItems: 'center', justifyContent: 'space-between'}}
            >
                <Typography variant="subtitle2" noWrap title={label}>
                    {label}
                </Typography>
                <IconButton size="small" onClick={onClose}>
                    <CloseIcon fontSize="small" />
                </IconButton>
            </Stack>
            <Divider sx={{my: 1}} />

            <Typography variant="caption" color="text.secondary">
                {t('grid_editor.config.variant', 'Display as')}
            </Typography>
            <ToggleButtonGroup
                size="small"
                exclusive
                fullWidth
                value={item.variant ?? 'chip'}
                onChange={(_e, v: ProfileItemVariant | null) =>
                    v && onChange({variant: v})
                }
                sx={{my: 1}}
            >
                <ToggleButton value="chip">
                    {t('grid_editor.config.variant.chip', 'Chip')}
                </ToggleButton>
                <ToggleButton value="text">
                    {t('grid_editor.config.variant.text', 'Text')}
                </ToggleButton>
            </ToggleButtonGroup>

            <FormControlLabel
                control={
                    <Switch
                        checked={item.showLabel ?? false}
                        onChange={(_e, checked) =>
                            onChange({showLabel: checked})
                        }
                    />
                }
                label={t('grid_editor.config.show_label', 'Show label')}
            />
            <FormControlLabel
                control={
                    <Switch
                        checked={item.displayEmpty ?? false}
                        onChange={(_e, checked) =>
                            onChange({displayEmpty: checked})
                        }
                    />
                }
                label={t('grid_editor.config.display_empty', 'Show if empty')}
            />
        </Paper>
    );
}
