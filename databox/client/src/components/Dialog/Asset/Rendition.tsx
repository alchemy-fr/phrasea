import {ReactNode} from 'react';
import {Asset, AssetRendition} from '../../../types';
import FilePlayer from '../../Media/Asset/FilePlayer';
import {Dimensions} from '../../Media/Asset/Players';
import {
    Button,
    Card,
    CardActions,
    CardContent,
    CardMedia,
    Skeleton,
    Typography,
} from '@mui/material';
import byteSize from 'byte-size';
import {useTranslation} from 'react-i18next';
import DownloadIcon from '@mui/icons-material/Download';
import SaveAsButton from '../../Media/Asset/Actions/SaveAsButton';

const cardProps = {
    elevation: 2,
    sx: {
        display: 'flex',
        mb: 2,
    },
};

const cardContentSx = {
    flexGrow: 1,
};

type Props = {
    asset: Asset;
    title: string | undefined;
    rendition: AssetRendition;
    maxDimensions: Dimensions;
};

export function Rendition({
    title,
    asset,
    maxDimensions,
    rendition: {name, file},
}: Props) {
    const {t} = useTranslation();

    return (
        <RenditionStructure
            title={name}
            maxDimensions={maxDimensions}
            media={
                file ? (
                    <FilePlayer
                        file={file}
                        title={title}
                        maxDimensions={maxDimensions}
                        autoPlayable={false}
                        controls={true}
                    />
                ) : undefined
            }
            info={
                file && (
                    <div>
                        {file.size && <>{byteSize(file.size).toString()} • </>}
                        {file.type}
                    </div>
                )
            }
            actions={
                <>
                    {file?.url && (
                        <>
                            <Button
                                startIcon={<DownloadIcon />}
                                href={file.url}
                                target={'_blank'}
                                rel={'noreferrer'}
                            >
                                {t('renditions.download', 'Download')}
                            </Button>
                            <SaveAsButton
                                asset={asset}
                                file={file}
                                variant={'outlined'}
                            />
                        </>
                    )}
                </>
            }
        />
    );
}

function RenditionStructure({
    title,
    info,
    media,
    actions,
    maxDimensions,
}: {
    title: ReactNode;
    info: ReactNode;
    media: ReactNode | undefined;
    actions: ReactNode;
    maxDimensions: Dimensions;
}) {
    return (
        <Card {...cardProps}>
            <CardMedia
                sx={theme => ({
                    ...maxDimensions,
                    display: 'flex',
                    justifyContent: 'center',
                    alignItems: 'center',
                    backgroundColor: theme.palette.grey['100'],
                })}
            >
                {media || ''}
            </CardMedia>
            <CardContent sx={cardContentSx}>
                <Typography component="div" variant="h5">
                    {title}
                </Typography>
                <Typography component="div" variant="body1">
                    {info}
                </Typography>

                <CardActions disableSpacing>{actions}</CardActions>
            </CardContent>
        </Card>
    );
}

export function RenditionSkeleton({
    maxDimensions,
}: {
    maxDimensions: Dimensions;
}) {
    return (
        <RenditionStructure
            title={<Skeleton variant={'text'} />}
            info={<Skeleton variant={'text'} width={'50%'} />}
            maxDimensions={maxDimensions}
            media={<Skeleton {...maxDimensions} variant={'rectangular'} />}
            actions={
                <Skeleton width={150} height={60} variant={'rectangular'} />
            }
        />
    );
}
