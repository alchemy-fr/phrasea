'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {routes} from '@/lib/routes';

export default function NotFound() {
    const {t} = useTranslation();

    return (
        <div className="flex min-h-[100dvh] flex-col items-center justify-center gap-3 p-8 text-center">
            <div className="text-6xl font-bold text-muted-foreground/40">
                404
            </div>
            <h1 className="text-xl font-semibold">
                {t('error.404.page', 'Page not found')}
            </h1>
            <Link
                href={routes.home()}
                className="text-primary underline-offset-4 hover:underline"
            >
                {t('error.404.back', 'Back to the dashboard')}
            </Link>
        </div>
    );
}
