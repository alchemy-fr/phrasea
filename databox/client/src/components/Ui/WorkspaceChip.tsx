import {PropsWithChildren} from "react";
import {Chip, ChipProps} from "@mui/material";

export const WorkspaceChip = ({
    children,
    ...props
}: PropsWithChildren<ChipProps>) => (
    <Chip {...props} color={'primary'} label={children || props.label}/>
);
