import {getPath, useModals, useParams} from '@alchemy/navigation';
import PageEditor, {
    OnPageSave,
} from '../components/Landing/Editor/PageEditor.tsx';
import {Container} from '@mui/material';
import {Page} from '../types.ts';
import {useCallback, useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {getPage, putPage} from '../api/page.ts';
import {routes} from '../routes.ts';
import PageEditDialog from '../components/Landing/Editor/form/PageEditDialog.tsx';

type Props = {};

export default function PageEditPage({}: Props) {
    const {id} = useParams();
    const [data, setData] = useState<Page | undefined>();
    const {openModal} = useModals();

    useEffect(() => {
        (async () => {
            setData(await getPage(id!));
        })();
    }, [id]);

    const onSave = useCallback<OnPageSave>(
        content => {
            putPage(id!, {
                data: content,
            });
        },
        [id]
    );

    const onEdit = useCallback(() => {
        openModal(PageEditDialog, {
            data: data!,
            onChange: d => setData(d),
        });
    }, [data]);

    const onPreview = useCallback(() => {
        if (!data) {
            return;
        }

        const slug = data?.slug;

        const uri = `${window.location.origin}${
            slug
                ? getPath(routes.pages, {
                      slug,
                  })
                : '/'
        }`;
        window.open(uri, '_blank');
    }, [data]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <>
            <Container>
                <PageEditor
                    data={data}
                    onSave={onSave}
                    onPreview={onPreview}
                    onEdit={onEdit}
                />
            </Container>
        </>
    );
}
