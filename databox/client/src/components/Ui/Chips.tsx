import {Chip, ChipProps, styled} from "@mui/material";
import React from "react";

export const WorkspaceChip = (props: ChipProps) => <Chip
    {...props}
    color={'primary'}
/>;

export const CollectionChip = (props: ChipProps) => <Chip
    {...props}
    sx={theme => ({
        bgcolor: theme.palette.grey[300],
        color: theme.palette.grey[900],
    })}
/>;

