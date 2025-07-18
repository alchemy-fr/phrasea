import {styled, Switch} from '@mui/material';
import {SortBy} from '../Filter';
import {TogglableSortBy} from './EditSortBy';
import {useSortable} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import DragHandleIcon from '@mui/icons-material/DragHandle';
import {grey} from '@mui/material/colors';
import {useTranslation} from 'react-i18next';
import {AttributeDefinition} from '../../../../types.ts';

export type OnChangeHandler = (
    sortBy: SortBy,
    enabled: boolean | undefined,
    way?: 0 | 1,
    grouped?: boolean | undefined
) => void;

type Props = {
    enabled: boolean;
    definition: AttributeDefinition;
    onChange: OnChangeHandler;
    sortBy: TogglableSortBy;
};

export default function SortByRow({sortBy, definition, onChange}: Props) {
    const {t} = useTranslation();
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

    if (!definition) {
        console.warn(`Missing definition for ${sortBy.a}`);
        return null;
    }

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        touchAction: 'manipulation',
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <tr ref={setNodeRef} style={style} {...attributes}>
            <td>
                <Switch
                    checked={sortBy.enabled}
                    onChange={(_e, value) => onChange(sortBy, value)}
                />
            </td>
            <td
                style={{
                    cursor: 'pointer',
                    userSelect: 'none',
                }}
                onClick={() => onChange(sortBy, !sortBy.enabled)}
            >
                {definition.nameTranslated ?? definition.name}
            </td>
            <td>
                <ToggleWay
                    onChange={(_e, value) =>
                        onChange(sortBy, true, value ? 1 : 0)
                    }
                    checked={isDesc}
                />
                <span
                    style={{
                        cursor: 'pointer',
                        userSelect: 'none',
                    }}
                    onClick={() =>
                        onChange(sortBy, true, Math.abs(sortBy.w - 1) as 0 | 1)
                    }
                >
                    {isDesc
                        ? t('sort_by_row.descendant', `Descendant`)
                        : t('sort_by_row.ascendant', `Ascendant`)}
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
                    <DragHandleIcon />
                </div>
            </td>
        </tr>
    );
}

const ToggleWay = styled(Switch)(({theme}) => ({
    'width': 60,
    'height': 34,
    'padding': 9,
    '& .MuiSwitch-switchBase': {
        'margin': 1,
        'padding': 0,
        'transform': 'translateX(6px)',
        '&.Mui-checked': {
            'color': '#fff',
            'transform': 'translateX(24px)',
            '& .MuiSwitch-thumb:before': {
                transform: `rotateZ(0)`,
                backgroundImage: `url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 0 20 20"><polygon fill="${encodeURIComponent(
                    '#fff'
                )}" points="0,5.01 10,14.98 20,5.01"/></svg>')`,
            },
            '& + .MuiSwitch-track': {
                opacity: 1,
                backgroundColor:
                    theme.palette.mode === 'dark' ? grey[600] : grey[400],
            },
        },
    },
    '& .MuiSwitch-thumb': {
        'backgroundColor': theme.palette.primary.main,
        'width': 28,
        'height': 28,
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
                '#fff'
            )}" points="0,5.01 10,14.98 20,5.01"/></svg>')`,
        },
    },
    '& .MuiSwitch-track': {
        opacity: 1,
        backgroundColor: theme.palette.mode === 'dark' ? grey[600] : grey[400],
        borderRadius: 20 / 2,
    },
}));
