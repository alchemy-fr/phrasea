'use client';

import {useEffect} from 'react';
import Link from 'next/link';
import {useRouter} from 'next/navigation';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {UploadCloudIcon} from 'lucide-react';
import {getAllTargets} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {FullPageLoader} from '@/components/ui/loader';
import {Alert} from '@/components/ui/misc';
import {Page} from '@/components/layout/AppShell';
import {getErrorMessage} from '@/lib/utils/errors';

export const targetsQueryKey = ['targets'] as const;

/**
 * The targets the user may upload to. A single one is opened right away: the
 * list is only a choice to make.
 */
export function TargetListScreen() {
    const {t} = useTranslation();
    const router = useRouter();
    const {
        data: targets,
        error,
        isPending,
    } = useQuery({
        queryKey: targetsQueryKey,
        queryFn: getAllTargets,
    });
    const single = targets?.length === 1 ? targets[0] : undefined;

    useEffect(() => {
        if (single) {
            router.replace(routes.upload(single.id));
        }
    }, [single, router]);

    if (isPending || single) {
        return <FullPageLoader />;
    }

    if (error) {
        return (
            <Page>
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            </Page>
        );
    }

    if (targets.length === 0) {
        return (
            <Page>
                <Alert data-testid="no-target">
                    {t(
                        'targets.none',
                        "You don't have access to any upload target."
                    )}
                </Alert>
            </Page>
        );
    }

    return (
        <Page title={t('targets.title', 'Where do you want to upload?')} wide>
            <ul
                data-testid="target-list"
                className="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3"
            >
                {targets.map(target => (
                    <li key={target.id}>
                        <Link
                            href={routes.upload(target.id)}
                            data-testid="target-card"
                            className="flex h-full flex-col gap-2 rounded-lg border bg-card p-5 text-card-foreground shadow-xs transition-colors hover:border-primary hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                        >
                            <span className="flex items-center gap-2 text-lg font-semibold">
                                <UploadCloudIcon className="size-5 shrink-0 text-primary" />
                                <span className="truncate">{target.name}</span>
                            </span>
                            {target.description ? (
                                <span className="line-clamp-4 text-sm whitespace-pre-line text-muted-foreground">
                                    {target.description}
                                </span>
                            ) : null}
                        </Link>
                    </li>
                ))}
            </ul>
        </Page>
    );
}
