import React from 'react';
import {Asset, Share} from '../../types.ts';
import {useQuery} from '@tanstack/react-query';
import {getPublicShare} from '../../api/asset.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import AssetShare from './AssetShare.tsx';
import ShareLogo from './ShareLogo.tsx';
import ShareTermsSection from './ShareTermsSection.tsx';
import ShareTermsDialog from './ShareTermsDialog.tsx';
import {hasAcceptedShareTerms} from './shareTermsStorage.ts';
import ShareAttachmentsSection from './ShareAttachmentsSection.tsx';
import {Box} from '@mui/material';

type Props = {
    id: string;
    token: string;
};

// "pending" until the browser storage has been checked after mount,
// so the server and the first client render stay identical.
type TermsState = 'pending' | 'required' | 'accepted';

export default function ShareView({id, token}: Props) {
    const {data, isSuccess} = useQuery<Share>({
        queryKey: ['share', id, token],
        queryFn: () => getPublicShare(id, token),
    });

    const terms = data?.terms ?? null;
    const [termsState, setTermsState] = React.useState<TermsState>('pending');

    React.useEffect(() => {
        if (!data) {
            return;
        }

        setTermsState(
            !terms || hasAcceptedShareTerms(data.id, terms)
                ? 'accepted'
                : 'required'
        );
    }, [data, terms]);

    if (!isSuccess) {
        return <FullPageLoader />;
    }

    const assets = (data.assets ?? []) as Asset[];

    return (
        <div
            style={{
                overflow: 'auto',
                height: '100vh',
            }}
        >
            <ShareLogo logo={data.logo} />

            {terms && termsState === 'required' ? (
                <ShareTermsDialog
                    shareId={data.id}
                    terms={terms}
                    onAccepted={() => setTermsState('accepted')}
                />
            ) : null}

            {termsState === 'accepted' ? (
                <>
                    {terms ? <ShareTermsSection terms={terms} /> : null}

                    {assets.map(asset => (
                        <Box
                            key={asset.id}
                            sx={{
                                mb: 3,
                            }}
                        >
                            <AssetShare asset={asset} />
                        </Box>
                    ))}

                    {data.attachments && data.attachments.length > 0 ? (
                        <ShareAttachmentsSection
                            attachments={data.attachments}
                        />
                    ) : null}
                </>
            ) : null}
        </div>
    );
}
