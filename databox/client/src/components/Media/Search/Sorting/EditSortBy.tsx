import React, {useCallback, useContext, useEffect, useMemo, useState} from 'react';
import {Box, Button, Typography} from "@mui/material";
import SaveIcon from "@mui/icons-material/Save";
import {useTranslation} from 'react-i18next';
import {SearchContext} from "../SearchContext";
import {getResolvedSortBy} from "../SearchProvider";
import {ResultContext} from "../ResultContext";
import {SortBy} from "../Filter";
import SortByRow, {OnChangeHandler} from "./SortByRow";
import {
    closestCenter,
    DragEndEvent,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
    DndContext,
} from "@dnd-kit/core";
import {arrayMove, SortableContext, verticalListSortingStrategy} from "@dnd-kit/sortable";

type Props = {
    onClose: () => void;
};

export type TogglableSortBy = {
    enabled: boolean;
    id: string;
} & SortBy;

export default function EditSortBy({
                                       onClose,
                                   }: Props) {
    const {sortBy, setSortBy} = useContext(SearchContext);
    const {facets} = useContext(ResultContext);
    const {t} = useTranslation();


    const list = useMemo<TogglableSortBy[]>(() => {
        const l: TogglableSortBy[] = [];
        getResolvedSortBy(sortBy).forEach(s => {
            l.push({
                ...s,
                id: s.a,
                enabled: true,
            });
        });

        if (facets) {
            Object.keys(facets).forEach(k => {
                if (l.some(s => s.a === k)) {
                    return;
                }

                const f = facets[k];

                l.push({
                    id: k,
                    a: k,
                    t: f.meta.title,
                    w: 0,
                    enabled: false,
                });
            });
        }

        return l;
    }, [facets, sortBy]);

    const [orders, setOrders] = useState<TogglableSortBy[]>(list);

    useEffect(() => {
        setOrders(list);
    }, [list]);

    const apply = useCallback(() => {
        setSortBy(orders.filter(s => s.enabled));
        onClose();
    }, [orders]);

    const reset = useCallback(() => {
        setSortBy([]);
        onClose();
    }, [orders]);

    const onChange = useCallback<OnChangeHandler>((sortBy, enabled, way) => {
        setOrders((prev) => {
            return prev.map(s => s.a === sortBy.a ? ({
                ...s,
                enabled: enabled ?? s.enabled,
                w: way ?? s.w,
            }) : s);
        });
    }, []);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor),
    );
    function handleDragEnd(event: DragEndEvent) {
        const {active, over} = event;

        if (over && active.id !== over.id) {
            const indexA = orders.findIndex(f => f.a === active.id);
            const indexB = orders.findIndex(f => f.a === over.id);

            setOrders(prev => arrayMove(prev, indexA, indexB));
        }
    }

    return <div>
        <Box
            sx={{
                px: 4,
                py: 1,
                width: {
                    md: 500
                }
            }}
        >
            <Typography
                sx={{
                    mb: 2,
                }}
            >
                {t('search.sort_by.title', 'Sort by')}
            </Typography>

            <table>
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={orders}
                        strategy={verticalListSortingStrategy}
                    >
                        {orders.map(s => <SortByRow
                            sortBy={s}
                            enabled={s.enabled}
                            key={s.a}
                            onChange={onChange}
                        />)}
                    </SortableContext>
                </DndContext>
            </table>
        </Box>
        <Box sx={{
            textAlign: 'right',
            p: 1,
            pb: 0,
        }}>
            <Button
                onClick={reset}
                color={'warning'}
            >
                {t('dialog.reset', 'Reset')}
            </Button>
            <Button
                sx={{
                    ml: 2,
                }}
                startIcon={<SaveIcon/>}
                onClick={apply}
                color={'primary'}
            >
                {t('dialog.apply', 'Apply')}
            </Button>
        </Box>
    </div>
}
