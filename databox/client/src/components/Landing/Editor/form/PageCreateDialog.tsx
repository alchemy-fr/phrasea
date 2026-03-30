import '../styles.scss';
import {Page} from '../../../../types.ts';
import {postPage} from '../../../../api/page.ts';
import PageEditFields from './PageEditFields.tsx';
import {AppDialog} from '@alchemy/phrasea-ui';
import {
    getPath,
    StackedModalProps,
    useModals,
    useNavigate,
} from '@alchemy/navigation';
import {useFormSubmit} from '@alchemy/api';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {routes} from '../../../../routes.ts';
import {Button} from '@mui/material';

type Props = {} & StackedModalProps;

export default function PageCreateDialog({modalIndex, open}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const navigate = useNavigate();

    const usedFormSubmit = useFormSubmit<Page>({
        defaultValues: {
            title: '',
            description: '',
            slug: '',
            enabled: true,
            public: true,
        } as Page,
        onSubmit: async data => {
            return await postPage(data);
        },
        onSuccess: data => {
            closeModal();
            navigate(
                getPath(routes.pageAdmin.routes.edit, {
                    id: data.id,
                })
            );
        },
        toastSuccess: t('pages.create.success', 'Page created successfully'),
    });

    const {handleSubmit, forbidNavigation, submitting} = usedFormSubmit;

    useDirtyFormPrompt(forbidNavigation, modalIndex);
    const formId = `page-edit-form`;

    return (
        <>
            <AppDialog
                open={open}
                modalIndex={modalIndex}
                onClose={closeModal}
                maxWidth={'sm'}
                title={t('pages.create.title', 'Create Page')}
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
                                {t('pages.create.submit', 'Create')}
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
