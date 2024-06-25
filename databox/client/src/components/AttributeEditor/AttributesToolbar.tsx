import Toolbar from "@mui/material/Toolbar";
import {IconButton} from "@mui/material";
import UndoIcon from '@mui/icons-material/Undo';
import RedoIcon from '@mui/icons-material/Redo';

type Props = {
    undo: (() => void) | undefined;
    redo: (() => void) | undefined;
};

export default function AttributesToolbar({
    undo,
    redo,
}: Props) {
    return <>
        <Toolbar>
            <IconButton
                disabled={!undo}
                onClick={undo}
            >
                <UndoIcon />
            </IconButton>
            <IconButton
                disabled={!redo}
                onClick={redo}>
                <RedoIcon />
            </IconButton>
        </Toolbar>
    </>
}
