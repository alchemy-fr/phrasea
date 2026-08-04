import React, {useMemo, useState} from 'react';
import {
    closestCenter,
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
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {
    Box,
    Chip,
    Divider,
    FormControlLabel,
    IconButton,
    List,
    ListItemButton,
    ListItemText,
    ListSubheader,
    MenuItem,
    Paper,
    Stack,
    Switch,
    TextField,
    ToggleButton,
    ToggleButtonGroup,
    Tooltip,
    Typography,
    useTheme,
} from '@mui/material';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import Button from '@mui/material/Button';
import CloseIcon from '@mui/icons-material/Close';
import ImageIcon from '@mui/icons-material/Image';
import CheckBoxOutlineBlankIcon from '@mui/icons-material/CheckBoxOutlineBlank';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import {useTranslation} from 'react-i18next';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {logError} from '@alchemy/core';
import {chipColorNames, defaultChipColors} from '@alchemy/phrasea-framework';
import {
    AttributeDefinitionOrBuiltIn,
    DisplayProfile,
    GridAnchor,
    GridRegion,
    ProfileItem,
    ProfileItemSection,
    ProfileItemSize,
    ProfileItemVariant,
    Workspace,
} from '../../../types';
import {AttributeType} from '../../../api/types.ts';
import {isRichCapable} from '../../AssetList/Layouts/Grid/resolveGridItem.ts';
import {getAttributeType} from '../../Media/Asset/Attribute/types/getAttributeType.ts';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import AttributeDefinitionLabel from './AttributeDefinitionLabel.tsx';
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

// The top corners are not droppable: they are reserved for the item controls
// (selection checkbox on the left, menu button on the right).
type OverCell = GridAnchor | 'controls-left' | 'controls-right';

const OVER_GRID: OverCell[][] = [
    ['controls-left', 'tc', 'controls-right'],
    ['ml', 'cc', 'mr'],
    ['bl', 'bc', 'br'],
];
const BAND: GridAnchor[] = ['l', 'c', 'r'];

const DRAG_PALETTE = 'palette:';
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
    const [query, setQuery] = useState('');

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

    // Same presentation as the Organize tab: built-ins first, remaining
    // definitions grouped under their workspace subheader, filtered by search.
    const paletteRows = useMemo(() => {
        const q = query.trim().toLowerCase();
        const rows: (
            | {kind: 'header'; id: string; label: string}
            | {kind: 'field'; def: AttributeDefinitionOrBuiltIn}
        )[] = [];
        let lastWorkspace: string | undefined;
        for (const def of availableDefinitions) {
            if (
                q &&
                !(def.displayName ?? def.name ?? '')
                    .toLowerCase()
                    .includes(q) &&
                !(def.name ?? '').toLowerCase().includes(q)
            ) {
                continue;
            }
            if (!def.builtIn && def.workspace) {
                const ws = def.workspace as Workspace;
                if (ws.id !== lastWorkspace) {
                    lastWorkspace = ws.id;
                    rows.push({
                        kind: 'header',
                        id: `ws-${ws.id}`,
                        label: ws.displayName,
                    });
                }
            }
            rows.push({kind: 'field', def});
        }
        return rows;
    }, [availableDefinitions, query]);

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

    // Persist the (re)ordering of every item whose placement actually changed.
    const persistOrder = (
        list: ProfileItem[],
        region: GridRegion,
        anchor: GridAnchor
    ) => {
        list.forEach((it, order) => {
            const p = it.placement;
            if (
                p?.region !== region ||
                p?.anchor !== anchor ||
                p?.order !== order
            ) {
                persistPatch(it, {placement: {region, anchor, order}});
            }
        });
    };

    // The cell the drop landed on (a cell background, or another placed item).
    const targetCell = (overId: string) => {
        const cell = parseCell(overId);
        if (cell) return {region: cell.region, anchor: cell.anchor};
        const overItem = gridItems.find(i => i.id === overId);
        return overItem?.placement
            ? {
                  region: overItem.placement.region,
                  anchor: overItem.placement.anchor,
              }
            : null;
    };

    const handleDragEnd = ({active, over}: DragEndEvent) => {
        setActiveDrag(null);
        if (!over) return;
        const activeId = active.id as string;
        const overId = over.id as string;

        const target = targetCell(overId);
        if (!target) return;
        const {region, anchor} = target;
        const overItem = gridItems.find(i => i.id === overId);

        if (activeId.startsWith(DRAG_PALETTE)) {
            const def = definitionsIndex[activeId.slice(DRAG_PALETTE.length)];
            if (!def) return;
            const item: ProfileItem = {
                ...attributeDefinitionToItem(def),
                section: ProfileItemSection.Grid,
                placement: {
                    region,
                    anchor,
                    order: anchorItems(region, anchor).length,
                },
            };
            addToList(data.id, [item]);
            return;
        }

        if (activeId === overId) return;
        const item = gridItems.find(i => i.id === activeId);
        if (!item?.placement) return;

        const sameCell =
            item.placement.region === region &&
            item.placement.anchor === anchor;

        if (sameCell && overItem) {
            // Reorder within the cell: arrayMove on the full list keeps the drag
            // direction correct (dropping below an item lands below it).
            const full = anchorItems(region, anchor);
            const oldIndex = full.findIndex(i => i.id === item.id);
            const newIndex = full.findIndex(i => i.id === overItem.id);
            if (oldIndex === -1 || newIndex === -1 || oldIndex === newIndex) {
                return;
            }
            persistOrder(arrayMove(full, oldIndex, newIndex), region, anchor);
            return;
        }

        // Cross-cell move (or drop on a cell background): insert before the
        // hovered item, else append.
        const list = anchorItems(region, anchor).filter(i => i.id !== item.id);
        const index = overItem
            ? list.findIndex(i => i.id === overItem.id)
            : list.length;
        list.splice(index < 0 ? list.length : index, 0, item);
        persistOrder(list, region, anchor);
    };

    const selected = gridItems.find(i => i.id === selectedId);

    if (!loaded || !data.items) {
        return <FullPageLoader />;
    }

    const renderCell = (region: GridRegion, anchor: GridAnchor) => {
        const cellItems = anchorItems(region, anchor);
        return (
            <AnchorCell
                key={anchor}
                region={region}
                anchor={anchor}
                itemIds={cellItems.map(i => i.id)}
            >
                {cellItems.map(item => (
                    <PlacedChip
                        key={item.id}
                        item={item}
                        label={getItemLabel(item, definitionsIndex)}
                        selected={item.id === selectedId}
                        onSelect={() => setSelectedId(item.id)}
                        onRemove={() => {
                            if (item.id === selectedId)
                                setSelectedId(undefined);
                            removeFromProfile(data.id, [item.id]);
                        }}
                    />
                ))}
            </AnchorCell>
        );
    };

    return (
        <>
            <DialogContent sx={{minHeight}}>
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
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
                            <TextField
                                type="search"
                                variant="standard"
                                fullWidth
                                placeholder={t('dialog.search', 'Search')}
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                sx={{px: 1, mt: 1}}
                            />
                            <List
                                dense
                                sx={{mt: 1, maxHeight: 360, overflow: 'auto'}}
                            >
                                {paletteRows.map(row =>
                                    row.kind === 'header' ? (
                                        <ListSubheader
                                            key={row.id}
                                            disableSticky
                                        >
                                            {row.label}
                                        </ListSubheader>
                                    ) : (
                                        <PaletteField
                                            key={row.def.id}
                                            def={row.def}
                                        />
                                    )
                                )}
                                {paletteRows.length === 0 && (
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                        sx={{p: 1}}
                                    >
                                        {query.trim()
                                            ? t(
                                                  'grid_editor.palette.no_match',
                                                  'No matching field'
                                              )
                                            : t(
                                                  'grid_editor.palette.empty',
                                                  'All fields are placed'
                                              )}
                                    </Typography>
                                )}
                            </List>
                        </Paper>

                        {/* Card canvas */}
                        <Box sx={{flexGrow: 1, minWidth: 260}}>
                            <Box sx={{maxWidth: 320, mx: 'auto'}}>
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
                                            row.map(a =>
                                                a === 'controls-left' ||
                                                a === 'controls-right' ? (
                                                    <ReservedCell
                                                        key={a}
                                                        side={a}
                                                    />
                                                ) : (
                                                    renderCell('over', a)
                                                )
                                            )
                                        )}
                                    </Box>
                                </Box>

                                <BandZone>
                                    {BAND.map(a => renderCell('below', a))}
                                </BandZone>
                            </Box>
                        </Box>

                        {/* Config panel */}
                        {selected &&
                            (() => {
                                const def =
                                    definitionsIndex[
                                        selected.definition ??
                                            selected.key ??
                                            ''
                                    ];
                                const rich = def ? isRichCapable(def) : false;
                                return (
                                    <ConfigPanel
                                        key={selected.id}
                                        item={selected}
                                        label={getItemLabel(
                                            selected,
                                            definitionsIndex
                                        )}
                                        type={def?.type}
                                        richCapable={rich}
                                        defaultVariant={
                                            rich
                                                ? ProfileItemVariant.Rich
                                                : ProfileItemVariant.Chip
                                        }
                                        onChange={changes =>
                                            persistPatch(selected, changes)
                                        }
                                        onClose={() => setSelectedId(undefined)}
                                    />
                                );
                            })()}
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
    const item = gridItems.find(i => i.id === activeId);
    return item ? getItemLabel(item, definitionsIndex) : '';
}

function PaletteField({def}: {def: AttributeDefinitionOrBuiltIn}) {
    const {attributes, listeners, setNodeRef, isDragging} = useDraggable({
        id: `${DRAG_PALETTE}${def.id}`,
    });
    return (
        <ListItemButton
            ref={setNodeRef}
            dense
            sx={{cursor: 'grab', opacity: isDragging ? 0.4 : 1}}
            {...listeners}
            {...attributes}
        >
            <ListItemText primary={<AttributeDefinitionLabel data={def} />} />
        </ListItemButton>
    );
}

// Non-droppable top corner, occupied by the item controls on the real card.
function ReservedCell({side}: {side: 'controls-left' | 'controls-right'}) {
    const {t} = useTranslation();

    return (
        <Tooltip
            title={t(
                'grid_editor.reserved_cell',
                'Reserved for asset controls'
            )}
        >
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent:
                        side === 'controls-left' ? 'flex-start' : 'flex-end',
                    minHeight: 28,
                    p: 0.25,
                    borderRadius: 0.5,
                    border: '1px dashed',
                    borderColor: 'divider',
                    color: 'action.disabled',
                }}
            >
                {side === 'controls-left' ? (
                    <CheckBoxOutlineBlankIcon fontSize="small" />
                ) : (
                    <MoreVertIcon fontSize="small" />
                )}
            </Box>
        </Tooltip>
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
    itemIds,
    children,
}: {
    region: GridRegion;
    anchor: GridAnchor;
    itemIds: string[];
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
            <SortableContext
                items={itemIds}
                strategy={verticalListSortingStrategy}
            >
                {children}
            </SortableContext>
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
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id: item.id});
    return (
        <Chip
            ref={setNodeRef}
            size="small"
            label={label}
            color={selected ? 'primary' : 'default'}
            variant={
                item.variant === ProfileItemVariant.Text ? 'outlined' : 'filled'
            }
            onClick={onSelect}
            onDelete={onRemove}
            deleteIcon={<CloseIcon />}
            sx={{
                cursor: 'grab',
                maxWidth: '100%',
                opacity: isDragging ? 0.4 : 1,
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            {...listeners}
            {...attributes}
        />
    );
}

function ColorPicker({
    value,
    onChange,
}: {
    value: string | undefined;
    onChange: (color: string) => void;
}) {
    const {t} = useTranslation();
    const theme = useTheme();
    const chipColors = {...defaultChipColors, ...(theme.palette.chips ?? {})};

    const swatchSx = (selected: boolean) => ({
        width: 22,
        height: 22,
        borderRadius: '50%',
        cursor: 'pointer',
        border: '2px solid',
        borderColor: selected ? 'primary.main' : 'divider',
        flex: '0 0 auto',
    });

    return (
        <Box sx={{display: 'flex', flexWrap: 'wrap', gap: 0.75, my: 1}}>
            <Tooltip title={t('grid_editor.config.color.default', 'Default')}>
                <Box
                    role="button"
                    onClick={() => onChange('')}
                    sx={{
                        ...swatchSx(!value),
                        // Diagonal strike marking the "no color" swatch.
                        background:
                            'linear-gradient(to top right, transparent 45%, currentColor 45%, currentColor 55%, transparent 55%)',
                        color: 'divider',
                    }}
                />
            </Tooltip>
            {chipColorNames.map(name => (
                <Tooltip key={name} title={name}>
                    <Box
                        role="button"
                        onClick={() => onChange(name)}
                        sx={{
                            ...swatchSx(value === name),
                            backgroundColor: chipColors[name].main,
                        }}
                    />
                </Tooltip>
            ))}
        </Box>
    );
}

function ConfigPanel({
    item,
    label,
    type,
    richCapable,
    defaultVariant,
    onChange,
    onClose,
}: {
    item: ProfileItem;
    label: string;
    type?: AttributeType;
    richCapable: boolean;
    defaultVariant: ProfileItemVariant;
    onChange: (changes: Partial<ProfileItem>) => void;
    onClose: () => void;
}) {
    const {t, i18n} = useTranslation();
    const variant = item.variant ?? defaultVariant;
    const availableFormats = type
        ? getAttributeType(type).getAvailableFormats({
              uiLocale: i18n.language,
              t,
          })
        : [];

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
                value={item.variant ?? defaultVariant}
                onChange={(_e, v: ProfileItemVariant | null) =>
                    v && onChange({variant: v})
                }
                sx={{my: 1}}
            >
                {richCapable && (
                    <ToggleButton value={ProfileItemVariant.Rich}>
                        {t('grid_editor.config.variant.rich', 'Rich')}
                    </ToggleButton>
                )}
                <ToggleButton value={ProfileItemVariant.Chip}>
                    {t('grid_editor.config.variant.chip', 'Chip')}
                </ToggleButton>
                <ToggleButton value={ProfileItemVariant.Text}>
                    {t('grid_editor.config.variant.text', 'Text')}
                </ToggleButton>
            </ToggleButtonGroup>

            {availableFormats.length > 0 && (
                <>
                    <Typography variant="caption" color="text.secondary">
                        {t('grid_editor.config.format', 'Format')}
                    </Typography>
                    <TextField
                        select
                        size="small"
                        fullWidth
                        value={item.format ?? ''}
                        onChange={e => onChange({format: e.target.value})}
                        sx={{my: 1}}
                    >
                        <MenuItem value="">
                            {t('grid_editor.config.format.default', 'Default')}
                        </MenuItem>
                        {availableFormats.map(f => (
                            <MenuItem key={f.name} value={f.name}>
                                {f.title}
                            </MenuItem>
                        ))}
                    </TextField>
                </>
            )}

            <Typography variant="caption" color="text.secondary">
                {t('grid_editor.config.size', 'Size')}
            </Typography>
            <ToggleButtonGroup
                size="small"
                exclusive
                fullWidth
                value={item.size ?? ProfileItemSize.Medium}
                onChange={(_e, v: ProfileItemSize | null) =>
                    v && onChange({size: v})
                }
                sx={{my: 1}}
            >
                <ToggleButton value={ProfileItemSize.Small}>
                    {t('grid_editor.config.size.small', 'S')}
                </ToggleButton>
                <ToggleButton value={ProfileItemSize.Medium}>
                    {t('grid_editor.config.size.medium', 'M')}
                </ToggleButton>
                <ToggleButton value={ProfileItemSize.Large}>
                    {t('grid_editor.config.size.large', 'L')}
                </ToggleButton>
            </ToggleButtonGroup>

            {(variant === ProfileItemVariant.Chip ||
                variant === ProfileItemVariant.Rich) && (
                <>
                    <Typography variant="caption" color="text.secondary">
                        {t('grid_editor.config.color', 'Chip color')}
                    </Typography>
                    <ColorPicker
                        value={item.color}
                        onChange={color => onChange({color})}
                    />
                </>
            )}

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
