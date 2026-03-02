import {Asset, Publication} from '../../../types.ts';
import {Box, Button, Typography} from '@mui/material';
import GetAppIcon from '@mui/icons-material/GetApp';
import {useTranslation} from 'react-i18next';
import {useDownload} from '../../../hooks/useDownload.ts';
import Description from '../layouts/common/Description.tsx';
import {getBestFieldTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper.ts';

type Props = {
    publication: Publication;
    asset: Asset;
    displayDownload?: boolean;
};

export default function AssetLegend({
    publication,
    asset,
    displayDownload,
}: Props) {
    const {t, i18n} = useTranslation();

    const onDownload = useDownload({
        publication,
    });

    const title = getBestFieldTranslatedValue(
        asset.translations,
        'title',
        asset.title,
        undefined,
        [i18n.language]
    );

    const description = getBestFieldTranslatedValue(
        asset.translations,
        'description',
        asset.description,
        undefined,
        [i18n.language]
    );

    return (
        <Box
            sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 2,
            }}
        >
            {title ? <Typography variant={'h1'}>{title}</Typography> : null}

            {displayDownload &&
                publication.downloadEnabled &&
                asset.downloadUrl && (
                    <div>
                        <Button
                            variant={'contained'}
                            onClick={() => onDownload(asset.downloadUrl)}
                            startIcon={<GetAppIcon />}
                        >
                            {t('publication.asset.download', 'Download')}
                        </Button>
                    </div>
                )}

            {description ? (
                <Typography variant={'body1'} component={'div'}>
                    <Description descriptionHtml={description} />
                </Typography>
            ) : null}
        </Box>
    );
}
