'use client';

import {useTranslation} from 'react-i18next';
import {AlertTriangleIcon} from 'lucide-react';
import {EmptyState} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';

export function SearchError({
    error,
    onRetry,
}: {
    error: string;
    onRetry: () => void;
}) {
    const {t} = useTranslation();

    return (
        <EmptyState
            className="h-full"
            icon={<AlertTriangleIcon className="text-destructive" />}
            title={t('search.error.title', 'Search failed')}
            description={<code className="text-xs break-all">{error}</code>}
            action={
                <Button variant="outline" onClick={onRetry}>
                    {t('common.retry', 'Retry')}
                </Button>
            }
        />
    );
}
