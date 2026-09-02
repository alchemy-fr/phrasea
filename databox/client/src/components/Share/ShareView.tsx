import {Asset, Share} from '../../types.ts';
import {useQuery} from '@tanstack/react-query';
import {getPublicShare} from '../../api/asset.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import AssetShare from './AssetShare.tsx';
import ShareLogo from './ShareLogo.tsx';
import ShareTermsSection from './ShareTermsSection.tsx';
import ShareAttachmentsSection from './ShareAttachmentsSection.tsx';
import {Box} from '@mui/material';

type Props = {
    id: string;
    token: string;
};

export default function ShareView({id, token}: Props) {
    const {data, isSuccess} = useQuery<Share>({
        queryKey: ['share', id, token],
        queryFn: () => getPublicShare(id, token),
    });

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

            {data.terms ? <ShareTermsSection terms={data.terms} /> : null}

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
                <ShareAttachmentsSection attachments={data.attachments} />
            ) : null}
        </div>
    );
}
