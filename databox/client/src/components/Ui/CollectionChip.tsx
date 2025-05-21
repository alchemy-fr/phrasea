import {PropsWithChildren} from "react";
import {Chip, ChipProps} from "@mui/material";

export const CollectionChip = ({
    children,
    ...props
}: PropsWithChildren<ChipProps>) => (
    <Chip
        {...props}
        sx={theme => ({
            bgcolor: theme.palette.grey[300],
            color: theme.palette.grey[900],
        })}
        label={children || props.label}
    />
);
