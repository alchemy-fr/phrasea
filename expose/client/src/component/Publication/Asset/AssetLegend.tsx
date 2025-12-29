import {Asset, Publication} from '../../../types.ts';
import {Box, Button, Typography} from '@mui/material';
import GetAppIcon from '@mui/icons-material/GetApp';
import {useTranslation} from 'react-i18next';

type Props = {
    publication: Publication;
    asset: Asset;
};

export default function AssetLegend({publication, asset}: Props) {
    const {t} = useTranslation();
    if (!asset.title && !asset.description) {
        return null;
    }

    return (
        <div>
            {asset.title ? (
                <Typography
                    variant={'h1'}
                    sx={{
                        mb: 2,
                    }}
                >
                    {asset.title}
                </Typography>
            ) : null}

            {publication.downloadEnabled && asset.downloadUrl && (
                <Box sx={{mb: 2}}>
                    <Button
                        variant={'contained'}
                        href={asset.downloadUrl}
                        startIcon={<GetAppIcon />}
                    >
                        {t('publication.asset.download', 'Download')}
                    </Button>
                </Box>
            )}

            {asset.description ? (
                <Typography variant={'body1'}>{asset.description}</Typography>
            ) : null}
        </div>
    );
}
