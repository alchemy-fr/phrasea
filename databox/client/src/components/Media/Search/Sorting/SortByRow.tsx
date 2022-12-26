import React from 'react';
import {styled, Switch} from "@mui/material";
import {SortBy} from "../Filter";
import {TogglableSortBy} from "./EditSortBy";
import {useSortable} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import DragHandleIcon from '@mui/icons-material/DragHandle';

export type OnChangeHandler = (sortBy: SortBy, enabled: boolean | undefined, way?: 0 | 1, grouped?: boolean | undefined) => void;

type Props = {
    enabled: boolean;
    onChange: OnChangeHandler;
    sortBy: TogglableSortBy;
};

export default function SortByRow({
                                      sortBy,
                                      onChange,
                                  }: Props) {

    const isDesc = sortBy.w === 1;

    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: sortBy.id,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        touchAction: 'manipulation',
        opacity: isDragging ? 0.5 : 1,
    };

    return <tr
        ref={setNodeRef}
        style={style}
        {...attributes}
    >
        <td>
            <Switch
                checked={sortBy.enabled}
                onChange={(e, value) => onChange(sortBy, value)}
            />
        </td>
        <td
            style={{
                cursor: 'pointer',
                userSelect: 'none',
            }}
            onClick={() => onChange(sortBy, !sortBy.enabled)}
        >
            {sortBy.t}
        </td>
        <td>
            <ToggleWay
                onChange={(e, value) => onChange(sortBy, true, value ? 1 : 0)}
                checked={isDesc}
            />
            <span
                style={{
                    cursor: 'pointer',
                    userSelect: 'none',
                }}
                onClick={() => onChange(sortBy, true, Math.abs(sortBy.w - 1) as 0 | 1)}
            >
                {isDesc ? 'Descendant' : 'Ascendant'}
            </span>
        </td>
        <td>
            <div
                style={{
                    marginLeft: 20,
                    cursor: 'move',
                    touchAction: 'none',
                }}
                {...listeners}
            >
                <DragHandleIcon/>
            </div>
        </td>
    </tr>
}

const ToggleWay = styled(Switch)(({theme}) => ({
    width: 62,
    height: 34,
    padding: 7,
    '& .MuiSwitch-switchBase': {
        margin: 1,
        padding: 0,
        transform: 'translateX(6px)',
        '&.Mui-checked': {
            color: '#fff',
            transform: 'translateX(22px)',
            '& .MuiSwitch-thumb:before': {
                transform: `rotateZ(0)`,
                backgroundImage: `url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 0 20 20"><polygon fill="${encodeURIComponent(
                    '#fff',
                )}" points="0,5.01 10,14.98 20,5.01"/></svg>')`,
            },
            '& + .MuiSwitch-track': {
                opacity: 1,
                backgroundColor: theme.palette.mode === 'dark' ? '#8796A5' : '#aab4be',
            },
        },
    },
    '& .MuiSwitch-thumb': {
        backgroundColor: theme.palette.mode === 'dark' ? '#003892' : '#001e3c',
        width: 32,
        height: 32,
        '&:before': {
            content: "''",
            position: 'absolute',
            width: '100%',
            height: '100%',
            left: 0,
            top: 0,
            transform: `rotateZ(180deg)`,
            backgroundRepeat: 'no-repeat',
            backgroundPosition: 'center',
            backgroundImage: `url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 0 20 20"><polygon fill="${encodeURIComponent(
                '#fff',
            )}" points="0,5.01 10,14.98 20,5.01"/></svg>')`,
        },
    },
    '& .MuiSwitch-track': {
        opacity: 1,
        backgroundColor: theme.palette.mode === 'dark' ? '#8796A5' : '#aab4be',
        borderRadius: 20 / 2,
    },
}));
