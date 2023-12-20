import AppDialog from './AppDialog.tsx';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {MuiThemeEditor} from '@alchemy/theme-editor';

type Props = {} & StackedModalProps;

export default function ThemeEditor({open, modalIndex}: Props) {
    const {closeModal} = useModals();

    const onClose = () => closeModal();

    return (
        <AppDialog modalIndex={modalIndex} open={open} onClose={onClose}>
            <MuiThemeEditor onClose={onClose} />
        </AppDialog>
    );
}
