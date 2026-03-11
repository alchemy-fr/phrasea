import {useParams} from '@alchemy/navigation';
import {Page} from '../types.ts';
import {useEffect, useState} from 'react';
import {getPageBySlug} from '../api/page.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import PageContent from '../components/Landing/PageContent.tsx';

type Props = {};

export default function PagePage({}: Props) {
    const {slug} = useParams();
    const [data, setData] = useState<Page>();

    useEffect(() => {
        (async () => {
            setData(await getPageBySlug(slug!));
        })();
    }, [slug]);

    if (!data) {
        return <FullPageLoader />;
    }

    return <PageContent data={data} />;
}
