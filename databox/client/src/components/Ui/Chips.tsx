import {Chip, ChipProps} from "@mui/material";

export const WorkspaceChip = (props: ChipProps) => <Chip
    {...props}
    color={'primary'}
/>;

export const CollectionChip = (props: ChipProps) => <Chip
    {...props}
    color={'default'}
/>;
