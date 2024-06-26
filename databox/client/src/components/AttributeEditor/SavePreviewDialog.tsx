import {AppDialog} from '@alchemy/phrasea-ui';
import type {StackedModalProps} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import ValueDiff, {ValueDiffProps} from "./ValueDiff.tsx";
import {useModals} from '@alchemy/navigation'

type Props = {
} & ValueDiffProps & StackedModalProps;

export default function SavePreviewDialog({
    open,
    modalIndex,
    ...props
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    return <AppDialog
        onClose={closeModal}
        open={open}
        modalIndex={modalIndex}
        title={t('attribute_editor.diff.dialog.title', 'Confirm Changes?')}
    >
        <ValueDiff
            {...props}
        />
    </AppDialog>
}
