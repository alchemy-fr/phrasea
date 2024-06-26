import {AppDialog} from '@alchemy/phrasea-ui';
import type {StackedModalProps} from '@alchemy/navigation';
import {useModals} from '@alchemy/navigation'
import {useTranslation} from 'react-i18next';
import ValueDiff, {ValueDiffProps} from "./ValueDiff.tsx";
import {AttributeBatchActionEnum} from "../../api/asset.ts";

type Props = {} & ValueDiffProps & StackedModalProps;

export default function SavePreviewDialog({
    open,
    modalIndex,
    actions,
    ...props
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const doSave = () => {
        const finalActions = actions.map(a => {
            if (a.action === AttributeBatchActionEnum.Delete) {
                return {
                    ...a,
                    value: undefined,
                }
            }

            return a;
        });


    }

    return <AppDialog
        onClose={closeModal}
        open={open}
        modalIndex={modalIndex}
        title={t('attribute_editor.diff.dialog.title', 'Confirm Changes?')}
    >
        <ValueDiff
            actions={actions}
            {...props}
        />
    </AppDialog>
}
