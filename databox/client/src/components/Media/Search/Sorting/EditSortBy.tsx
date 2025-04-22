import React, {
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import {
    Box,
    Button,
    Checkbox,
    FormControlLabel,
    ListItem,
    ListItemText,
    Typography,
} from '@mui/material';
import SaveIcon from '@mui/icons-material/Save';
import {useTranslation} from 'react-i18next';
import {SearchContext} from '../SearchContext';
import {getResolvedSortBy} from '../SearchProvider';
import {SortBy} from '../Filter';
import SortByRow, {OnChangeHandler} from './SortByRow';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {BuiltInFilter} from '../search';
import {AttributeDefinition} from "../../../../types.ts";
import {AttributeDefinitionsIndex} from "../../../../store/attributeDeifnitionStore.ts";

export type TogglableSortBy = {
    enabled: boolean;
    id: string;
} & SortBy;

type Props = {
    definitions: AttributeDefinition[];
    definitionsIndex: AttributeDefinitionsIndex;
    onClose: () => void;
};

export default function EditSortBy({onClose, definitions, definitionsIndex}: Props) {
    const {sortBy, setSortBy} = useContext(SearchContext)!;
    const {t} = useTranslation();
    const [grouped, setGrouped] = React.useState(
        sortBy.length === 0 ? true : sortBy.some(s => s.g)
    );

    const list = useMemo<TogglableSortBy[]>(() => {
        const l: TogglableSortBy[] = [];
        getResolvedSortBy(sortBy).forEach(s => {
            l.push({
                ...s,
                id: s.a,
                enabled: true,
            });
        });

        if (definitions) {
            definitions
                .filter(d => d.sortable)
                .forEach(d => {
                    if (l.some(s => s.a === d.searchSlug)) {
                        return;
                    }

                l.push({
                    id: d.searchSlug,
                    a: d.searchSlug,
                    w: 0,
                    g: false,
                    enabled: false,
                });
            });
        }

        return l;
    }, [definitions, sortBy]);

    const [orders, setOrders] = useState<TogglableSortBy[]>(list);

    const enabledOrders = orders.filter(s => s.enabled);
    const groupDisabled =
        enabledOrders.length > 0 && enabledOrders[0].a === BuiltInFilter.Score;

    useEffect(() => {
        setOrders(list);
    }, [list]);

    const apply = useCallback(() => {
        const newSortBy = orders
            .filter(s => s.enabled)
            .map(s => ({
                w: s.w,
                a: s.a,
                g: false,
            }));

        if (!groupDisabled && grouped && newSortBy.length > 0) {
            newSortBy[0].g = true;
        }

        setSortBy(newSortBy);
        onClose();
    }, [orders, grouped]);

    const reset = useCallback(() => {
        setSortBy([]);
        onClose();
    }, [orders]);

    const onChange = useCallback<OnChangeHandler>(
        (sortBy, enabled, way, grouped) => {
            setOrders(prev => {
                return prev.map(s =>
                    s.a === sortBy.a
                        ? {
                              ...s,
                              enabled: enabled ?? s.enabled,
                              w: way ?? s.w,
                              g: grouped ?? s.g,
                          }
                        : s
                );
            });
        },
        []
    );

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor)
    );

    function handleDragEnd(event: DragEndEvent) {
        const {active, over} = event;

        if (over && active.id !== over.id) {
            const indexA = orders.findIndex(f => f.a === active.id);
            const indexB = orders.findIndex(f => f.a === over.id);

            setOrders(prev => {
                const n = arrayMove(prev, indexA, indexB);
                n[indexB].enabled = true;

                return n;
            });
        }
    }

    return (
        <div>
            <Box
                sx={{
                    px: 4,
                    py: 1,
                    width: {
                        md: 500,
                    },
                }}
            >
                <Typography
                    sx={{
                        mb: 2,
                    }}
                >
                    {t('search.sort_by.title', 'Sort by')}
                </Typography>

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <table>
                        <tbody>
                            <SortableContext
                                items={orders}
                                strategy={verticalListSortingStrategy}
                            >
                                {orders.map(s => (
                                    <SortByRow
                                        sortBy={s}
                                        definition={definitionsIndex[s.a]}
                                        enabled={s.enabled}
                                        key={s.a}
                                        onChange={onChange}
                                    />
                                ))}
                            </SortableContext>
                        </tbody>
                    </table>
                </DndContext>
            </Box>
            <Box
                sx={{
                    textAlign: 'right',
                    p: 1,
                    pb: 0,
                }}
            >
                <Box
                    sx={{
                        display: 'inline-block',
                        mr: 2,
                    }}
                >
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={grouped}
                                onChange={(_e, value) => setGrouped(value)}
                                disabled={groupDisabled}
                            />
                        }
                        label={
                            <ListItem disableGutters={true}>
                                <ListItemText
                                    primary={t(
                                        'edit_sort_by.group_by_sections',
                                        `Group by sections`
                                    )}
                                    secondary={t(
                                        'edit_sort_by.add_group_separators_between_results',
                                        `Add group separators between results`
                                    )}
                                ></ListItemText>
                            </ListItem>
                        }
                        labelPlacement="end"
                    />
                </Box>
                <Button onClick={reset} color={'warning'}>
                    {t('dialog.reset', 'Reset')}
                </Button>
                <Button
                    sx={{
                        ml: 2,
                    }}
                    startIcon={<SaveIcon />}
                    onClick={apply}
                    color={'primary'}
                >
                    {t('dialog.apply', 'Apply')}
                </Button>
            </Box>
        </div>
    );
}
