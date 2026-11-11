'use client';

import {useEffect} from 'react';
import {useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {LinkIcon} from 'lucide-react';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {isApiError} from '@/lib/api/http';
import {resolveShare} from '@/lib/api/shares';
import {storeShareToken} from '@/lib/api/shareTokens';
import {publicationKeys} from '@/lib/api/publications';
import {routes} from '@/lib/routes';
import {SecurityCard} from '@/features/security/SecurityScreen';
import {ErrorScreen} from '@/components/ErrorScreen';

/**
 * The permalink of a share link: keeps its token, which the API calls carry
 * from then on, and opens the shared publication.
 */
export function ShareLinkScreen({token}: {token: string}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const {data, error, refetch} = useQuery({
        queryKey: ['shares', 'resolve', token],
        queryFn: () => resolveShare(token),
        retry: false,
        staleTime: Infinity,
    });

    useEffect(() => {
        if (!data) {
            return;
        }
        storeShareToken(token);
        // Loaded without the token, the publication would stay locked
        queryClient.removeQueries({queryKey: publicationKeys.all});
        router.replace(routes.publication(data.publication));
    }, [data, token, queryClient, router]);

    if (error) {
        if (isApiError(error, 404)) {
            return (
                <SecurityCard
                    testId="share-link-invalid"
                    icon={<LinkIcon />}
                    title={t('share.invalid.title', 'Invalid link')}
                >
                    <p className="text-center text-sm text-muted-foreground">
                        {t(
                            'share.invalid.description',
                            'This link is not valid, has expired or has been revoked.'
                        )}
                    </p>
                </SecurityCard>
            );
        }

        return <ErrorScreen error={error} onRetry={() => refetch()} />;
    }

    return <FullPageLoader />;
}
