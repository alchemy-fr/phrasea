'use client';

import {useParams} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {PublicationShell} from '@/components/layout/PublicationShell';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {ErrorScreen} from '@/components/ErrorScreen';
import {SecurityScreen} from '@/features/security/SecurityScreen';
import {useTranslateField} from '@/lib/utils/translate';
import {usePublication, usePublicationViewLog} from './usePublication';
import {PublicationCss} from './PublicationCss';
import {PublicationTree} from './PublicationTree';
import {PublicationView} from './PublicationView';
import {TermsGate} from './TermsGate';

/**
 * A publication, by slug or id, with the asset of the URL opened: the
 * security screen when the user has no access to it yet, its terms to
 * accept, then its content.
 */
export function PublicationScreen({publicationKey}: {publicationKey: string}) {
    const {t} = useTranslation();
    const asset = useParams<{asset?: string}>().asset;
    const assetKey = asset ? decodeURIComponent(asset) : undefined;
    const translate = useTranslateField();
    const {
        data: publication,
        error,
        isPending,
        refetch,
    } = usePublication(publicationKey);
    const authorized = !!publication?.authorized;
    usePublicationViewLog(publication, authorized);
    const title = publication ? translate(publication, 'title') : undefined;

    let content;
    if (isPending) {
        content = <FullPageLoader />;
    } else if (error || !publication) {
        content = <ErrorScreen error={error} onRetry={() => refetch()} />;
    } else if (!publication.authorized) {
        content = <SecurityScreen publication={publication} reload={refetch} />;
    } else {
        content = (
            <TermsGate publication={publication}>
                <PublicationView
                    publication={publication}
                    assetKey={assetKey}
                />
            </TermsGate>
        );
    }

    return (
        <PublicationShell
            navigation={
                publication?.authorized
                    ? close => (
                          <PublicationTree
                              publication={publication}
                              onNavigate={close}
                          />
                      )
                    : undefined
            }
        >
            <title>
                {title
                    ? `${title} · Expose`
                    : t('publication.page_title', 'Publication · Expose')}
            </title>
            {authorized ? <PublicationCss href={publication?.cssLink} /> : null}
            {content}
        </PublicationShell>
    );
}
