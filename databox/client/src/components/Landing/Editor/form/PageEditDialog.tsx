import '../styles.scss';
import {Page} from '../../../../types.ts';
import {putPage} from '../../../../api/page.ts';
import PageEditFields from './PageEditFields.tsx';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useFormSubmit} from '@alchemy/api';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {Button} from '@mui/material';

type Props = {
    data: Page;
    onChange?: (data: Page) => void;
} & StackedModalProps;

export default function PageEditDialog({
    data,
    onChange,
    modalIndex,
    open,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const usedFormSubmit = useFormSubmit<Page>({
        defaultValues: data,
        onSubmit: async data => {
            delete data.data;

            return await putPage(data.id, data);
        },
        onSuccess: data => {
            onChange?.(data);
            closeModal();
        },
        toastSuccess: t('pages.edit.success', 'Page saved successfully'),
    });

    const {handleSubmit, forbidNavigation, submitting} = usedFormSubmit;

    useDirtyFormPrompt(forbidNavigation, modalIndex);
    const formId = `page-edit-form`;

    return (
        <>
            <AppDialog
                modalIndex={modalIndex}
                open={open}
                onClose={closeModal}
                maxWidth={'sm'}
                title={t('pages.edit.title', 'Edit Page')}
                actions={({onClose}) => {
                    return (
                        <>
                            <Button
                                onClick={() => {
                                    onClose();
                                }}
                            >
                                {t('common.cancel', 'Cancel')}
                            </Button>
                            <Button
                                form={formId}
                                type={'submit'}
                                variant={'contained'}
                                disabled={submitting}
                                loading={submitting}
                            >
                                {t('pages.edit.submit', 'Save')}
                            </Button>
                        </>
                    );
                }}
            >
                <form id={formId} onSubmit={handleSubmit}>
                    <PageEditFields usedFormSubmit={usedFormSubmit} />
                </form>
            </AppDialog>
        </>
    );
}
