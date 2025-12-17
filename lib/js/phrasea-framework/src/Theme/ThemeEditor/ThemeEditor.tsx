import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import MuiThemeEditor from './MuiThemeEditor';

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
