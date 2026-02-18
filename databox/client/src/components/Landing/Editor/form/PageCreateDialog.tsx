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
            return await postPage({
                title: data.title,
                slug: data.slug,
            });
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

    const {handleSubmit, forbidNavigation} = usedFormSubmit;

    useDirtyFormPrompt(forbidNavigation, modalIndex);

    return (
        <>
            <AppDialog
                open={open}
                modalIndex={modalIndex}
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
