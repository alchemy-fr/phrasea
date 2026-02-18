import {routes} from '../routes.ts';
import {getPath, useNavigate} from '@alchemy/navigation';
import {useEffect, useState} from 'react';
import {getPageBySlug} from '../api/page.ts';
import {Page} from '../types.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import PageContent from '../components/Landing/PageContent.tsx';
import {isErrorOfCode} from '@alchemy/api';

type Props = {};

export default function HomePage({}: Props) {
    const navigate = useNavigate();
    const [page, setPage] = useState<Page | undefined>();

    useEffect(() => {
        (async () => {
            const r = () => navigate(getPath(routes.assets));

            try {
                const page = await getPageBySlug('', {
                    handledErrorStatuses: [404],
                });
                setPage(page);
                if (!page) {
                    r();
                }
            } catch (e) {
                if (isErrorOfCode(e, [404])) {
                    r();
                }
            }
        })();
    }, [navigate]);

    if (!page) {
        return <FullPageLoader />;
    }

    return <PageContent data={page} />;
}
