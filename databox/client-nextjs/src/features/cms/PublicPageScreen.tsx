'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {FileQuestionIcon} from 'lucide-react';
import {getPageBySlug} from '@/lib/api/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState} from '@/components/ui/misc';
import {PageRenderer} from './PageRenderer';

export function PublicPageScreen({slug}: {slug: string}) {
    const {t} = useTranslation();
    const page = useQuery({
        queryKey: ['page-by-slug', slug],
        queryFn: () => getPageBySlug(slug),
        retry: false,
    });

    if (page.isLoading) {
        return <FullPageLoader />;
    }
    if (!page.data) {
        return (
            <EmptyState
                className="h-full"
                icon={<FileQuestionIcon />}
                title={t('cms.not_found', 'Page not found')}
            />
        );
    }

    return <PageRenderer page={page.data} />;
}
