import React from 'react';
import {Checkbox, ListItemButton, ListItemSecondaryAction, ListItemText} from "@mui/material";
import {LabelledBucketValue} from "../Facets";

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
    const {item, label, value} = labelValue;

    return <ListItemButton
        onClick={onClick}
    >
        <ListItemText secondary={`${label} (${count})`}/>
        <ListItemSecondaryAction>
            <Checkbox
                edge="end"
                onChange={onClick}
                checked={selected}
                inputProps={{'aria-labelledby': value.toString()}}
            />
        </ListItemSecondaryAction>
    </ListItemButton>
}
