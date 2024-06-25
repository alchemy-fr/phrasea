import Toolbar from "@mui/material/Toolbar";
import {Button, Divider, IconButton} from "@mui/material";
import UndoIcon from '@mui/icons-material/Undo';
import RedoIcon from '@mui/icons-material/Redo';
import {useTranslation} from 'react-i18next';
import SaveIcon from '@mui/icons-material/Save';

type Props = {
    undo: (() => void) | undefined;
    redo: (() => void) | undefined;
    hasChanges: boolean;
    onSave: () => Promise<void>;
};

export default function AttributesToolbar({
    undo,
    redo,
    hasChanges,
    onSave,
}: Props) {
    const {t} = useTranslation();
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
            <Divider orientation="vertical" flexItem />
            <Button
                onClick={onSave}
                startIcon={<SaveIcon/>}
                disabled={!hasChanges}
            >
                {t('attribute_editor.save.label', 'Save')}
            </Button>
        </Toolbar>
    </>
}
