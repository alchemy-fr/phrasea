import {Box, IconButton} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import DeleteIcon from "@mui/icons-material/Delete";
import {stopPropagation} from "../../lib/stdFuncs.ts";

type Props = {
    onAdd: () => void;
    onRemove: () => void;
    checked: boolean;
};

export default function AssetToggleOverlay({
    onAdd,
    onRemove,
    checked,
}: Props) {
    return <>
        <Box
            onMouseDown={stopPropagation}
            sx={{
                display: 'flex',
                justifyContent: 'space-around',
                alignItems: 'center',
                position: 'absolute',
                zIndex: 1,
                bottom: 0,
                width: '100%',
            }}
        >
            <IconButton
                color={'primary'}
                size={'large'}
                onClick={onAdd}
                disabled={checked}
            >
                <AddIcon/>
            </IconButton>
            <IconButton
                color={'primary'}
                size={'large'}
                onClick={onRemove}
                disabled={!checked}
            >
                <DeleteIcon/>
            </IconButton>
        </Box>
    </>
}
