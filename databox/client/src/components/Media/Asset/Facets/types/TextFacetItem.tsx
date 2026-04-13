import {
    Checkbox,
    ListItem,
    ListItemButton,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {stopPropagation} from '../../../../../lib/stdFuncs.ts';
import {LabelledBucketValue} from '../facetTypes.ts';

type Props = {
    onClick: () => void;
    selected: boolean;
    labelValue: LabelledBucketValue;
    count: number;
};

export type {Props as ListFacetItemProps};

export default function TextFacetItem({
    onClick,
    selected,
    labelValue,
    count,
}: Props) {
    const {label, value} = labelValue;

    return (
        <ListItem
            onClick={onClick}
            secondaryAction={
                <Checkbox
                    edge="end"
                    onChange={onClick}
                    onClick={stopPropagation}
                    checked={selected}
                    inputProps={{'aria-labelledby': value.toString()}}
                />
            }
        >
            <ListItemText secondary={`${label} (${count})`} />
        </ListItem>
    );
}
