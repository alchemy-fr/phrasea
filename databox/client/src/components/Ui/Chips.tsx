import {Chip, ChipProps} from "@mui/material";
import React from "react";

export const WorkspaceChip = (props: ChipProps) => <Chip
    {...props}
    color={'primary'}
/>;

export const CollectionChip = (props: ChipProps) => <Chip
    {...props}
    sx={theme => ({
        ml: 1,
        bgcolor: theme.palette.grey[300],
        color: theme.palette.grey[900],
    })}
/>;

export const TagChip = (props: ChipProps) => <Chip
    {...props}
    sx={theme => ({
        ml: 1,
        bgcolor: 'info.main',
        color: 'info.constrastText',
    })}
/>;
