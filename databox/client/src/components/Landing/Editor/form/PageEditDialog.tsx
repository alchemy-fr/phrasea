import '../styles.scss';
import {Page} from '../../../../types.ts';
import {putPage} from '../../../../api/page.ts';
import PageEditFields from './PageEditFields.tsx';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useFormSubmit} from '@alchemy/api';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';

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

    const {handleSubmit, forbidNavigation} = usedFormSubmit;

    useDirtyFormPrompt(forbidNavigation, modalIndex);

    return (
        <>
            <AppDialog
                modalIndex={modalIndex}
                open={open}
                onClose={closeModal}
                maxWidth={'sm'}
                title={t('pages.edit.title', 'Edit Page')}
            >
                <form onSubmit={handleSubmit}>
                    <PageEditFields
                        usedFormSubmit={usedFormSubmit}
                        submitLabel={t('pages.edit.submit', 'Save')}
                    />
                </form>
            </AppDialog>
        </>
    );
}
