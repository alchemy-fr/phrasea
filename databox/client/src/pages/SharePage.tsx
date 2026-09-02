import {useParams} from '@alchemy/navigation';
import {Asset, Share} from '../types.ts';
import {useQuery} from '@tanstack/react-query';
import {getPublicShare} from '../api/asset.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import AssetShare from '../components/Share/AssetShare.tsx';
import ShareLogo from '../components/Share/ShareLogo.tsx';
import ShareTermsSection from '../components/Share/ShareTermsSection.tsx';
import ShareAttachmentsSection from '../components/Share/ShareAttachmentsSection.tsx';
import {Box} from '@mui/material';

type Props = {};

export default function SharePage({}: Props) {
    const {id, token} = useParams() as {id: string; token: string};

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
