import {Chip, ChipProps} from "@mui/material";
import React from "react";

export const WorkspaceChip = (props: ChipProps) => <Chip
    {...props}
    color={'primary'}
/>;

export const CollectionChip = ({
                                   inverted,
                                   ...props
                               }: {
    inverted?: boolean;
} & ChipProps) => <Chip
    {...props}
    sx={theme => ({
        ml: 1,
        bgcolor: theme.palette.grey[300],
        color: theme.palette.grey[900],
    })}
/>;
