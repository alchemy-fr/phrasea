import {getPath, useParams} from '@alchemy/navigation';
import PageEditor, {
    OnPageSave,
} from '../components/Landing/Editor/PageEditor.tsx';
import {Container} from '@mui/material';
import {Page} from '../types.ts';
import {useCallback, useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {getPage, putPage} from '../api/page.ts';
import {routes} from '../routes.ts';

type Props = {};

export default function PageEditPage({}: Props) {
    const {id} = useParams();
    const [data, setData] = useState<Page | undefined>();

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
    const onPreview = useCallback(() => {
        const uri = `${window.location.origin}${getPath(routes.pages, {
            slug: data?.slug,
        })}`;
        // Open new tab to uri
        window.open(uri, '_blank');
    }, [data]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <>
            <Container>
                <PageEditor data={data} onSave={onSave} onPreview={onPreview} />
            </Container>
        </>
    );
}
