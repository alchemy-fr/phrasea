import {Asset, Publication} from '../../../types.ts';
import {Box, Button, Typography} from '@mui/material';
import GetAppIcon from '@mui/icons-material/GetApp';
import {useTranslation} from 'react-i18next';
import {useDownload} from '../../../hooks/useDownload.ts';
import Description from '../layouts/common/Description.tsx';

type Props = {
    publication: Publication;
    asset: Asset;
};

export default function AssetLegend({publication, asset}: Props) {
    const {t} = useTranslation();

    const onDownload = useDownload({
        url: asset.downloadUrl,
        publication,
    });

    if (!asset.title && !asset.description && !publication.downloadEnabled) {
        return null;
    }

    return (
        <Box
            sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 2,
            }}
        >
            {asset.title ? (
                <Typography variant={'h1'}>{asset.title}</Typography>
            ) : null}

            {publication.downloadEnabled && asset.downloadUrl && (
                <div>
                    <Button
                        variant={'contained'}
                        onClick={onDownload}
                        startIcon={<GetAppIcon />}
                    >
                        {t('publication.asset.download', 'Download')}
                    </Button>
                </div>
            )}

            {asset.description ? (
                <Typography variant={'body1'} component={'div'}>
                    <Description descriptionHtml={asset.description} />
                </Typography>
            ) : null}
        </Box>
    );
}
