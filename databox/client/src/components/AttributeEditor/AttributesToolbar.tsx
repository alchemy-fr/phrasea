import {Box, Button, Divider, IconButton} from '@mui/material';
import UndoIcon from '@mui/icons-material/Undo';
import RedoIcon from '@mui/icons-material/Redo';
import {useTranslation} from 'react-i18next';
import SaveIcon from '@mui/icons-material/Save';
import CloseIcon from '@mui/icons-material/Close';
import {useModals} from '@alchemy/navigation';
import ConfirmDialog from '../Ui/ConfirmDialog.tsx';
import RestartAltIcon from '@mui/icons-material/RestartAlt';

type Props = {
    undo: (() => void) | undefined;
    redo: (() => void) | undefined;
    hasChanges: boolean;
    onSave: () => Promise<void>;
    onClose: () => void;
    resetSelection: () => void;
};

export default function AttributesToolbar({
    undo,
    redo,
    hasChanges,
    onSave,
    onClose,
    resetSelection,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    const closeHandler = () => {
        if (hasChanges) {
            openModal(ConfirmDialog, {
                onConfirm: async () => onClose(),
                children: (
                    <>
                        {t(
                            'attribute_editor.discard_changes.desc',
                            `All the changes you've made on assets are not applied and will be discarded.`
                        )}
                    </>
                ),
                title: t(
                    'attribute_editor.discard_changes.title',
                    'Discard changes?'
                ) as string,
                confirmLabel: t(
                    'attribute_editor.discard_changes.confirm',
                    'Yes, discard'
                ),
            });
        } else {
            onClose();
        }
    };

    return (
        <Box
            sx={{
                'display': 'flex',
                'alignItems': 'center',
                'border': '1px solid',
                'borderColor': 'divider',
                'borderRadius': 1,
                'p': 1,
                'bgcolor': 'background.paper',
                'color': 'text.secondary',
                '& hr': {
                    mx: 1,
                },
            }}
        >
            <Button
                onClick={onSave}
                startIcon={<SaveIcon />}
                disabled={!hasChanges}
            >
                {t('attribute_editor.save.label', 'Save')}
            </Button>

            <Button
                onClick={closeHandler}
                startIcon={<CloseIcon />}
                sx={{ml: 2}}
            >
                {t('attribute_editor.cancel.label', 'Cancel')}
            </Button>

            <Divider orientation="vertical" flexItem />

            <IconButton disabled={!undo} onClick={undo}>
                <UndoIcon />
            </IconButton>
            <IconButton disabled={!redo} onClick={redo}>
                <RedoIcon />
            </IconButton>
            <IconButton disabled={!undo} onClick={resetSelection}>
                <RestartAltIcon />
            </IconButton>
        </Box>
    );
}
