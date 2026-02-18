import {getPath, useNavigate} from '@alchemy/navigation';

import {Container, Typography} from '@mui/material';
import {postPage} from '../api/page.ts';
import {routes} from '../routes.ts';
import {useFormSubmit} from '@alchemy/api';
import {useTranslation} from 'react-i18next';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import PageEditFields from '../components/Landing/Editor/form/PageEditFields.tsx';
import {Page} from '../types.ts';

type Props = {};

export default function PageCreatePage({}: Props) {
    const navigate = useNavigate();
    const {t} = useTranslation();

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
            navigate(
                getPath(routes.pageAdmin.routes.edit, {
                    id: data.id,
                })
            );
        },
        toastSuccess: t('pages.create.success', 'Page created successfully'),
    });

    const {handleSubmit, forbidNavigation} = usedFormSubmit;

    useDirtyFormPrompt(forbidNavigation);

    return (
        <>
            <Container>
                <Typography
                    variant={'h2'}
                    sx={{
                        mb: 2,
                    }}
                >
                    {t('pages.create.title', 'Create Page')}
                </Typography>
                <form onSubmit={handleSubmit}>
                    <PageEditFields
                        usedFormSubmit={usedFormSubmit}
                        submitLabel={t('pages.create.submit', 'Create Page')}
                    />
                </form>
            </Container>
        </>
    );
}
