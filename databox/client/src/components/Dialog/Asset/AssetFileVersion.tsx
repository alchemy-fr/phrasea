import React, {ReactNode} from 'react';
import {Asset, AssetFileVersion} from "../../../types";
import FilePlayer from "../../Media/Asset/FilePlayer";
import {Dimensions} from "../../Media/Asset/Players";
import {Button, Card, CardActions, CardContent, CardMedia, Skeleton, Typography} from "@mui/material";
import byteSize from "byte-size";
import {useTranslation} from 'react-i18next';
import DownloadIcon from '@mui/icons-material/Download';
import SaveAsButton from "../../Media/Asset/Actions/SaveAsButton";
import Date from "../../Ui/Date";

const cardProps = {
    elevation: 2,
    sx: {
        display: 'flex',
        mb: 2,
    }
}

const cardContentSx = {
    flexGrow: 1,
}

type Props = {
    asset: Asset;
    version: AssetFileVersion;
    maxDimensions: Dimensions;
};

export function AssetFileVersionCard({
    version: {
        file,
        name,
        createdAt,
    },
    asset,
    maxDimensions,
}: Props) {
    const {t} = useTranslation();

    return <AssetFileVersionStructure
        name={name}
        maxDimensions={maxDimensions}
        media={file ? <FilePlayer
            file={file}
            title={name}
            maxDimensions={maxDimensions}
            autoPlayable={false}
            controls={true}
        /> : undefined}
        info={file && <div>
            {file && <div>
                {file.size && <>{byteSize(file.size).toString()} •{' '}</>}
                {file.type}
            </div>}
            <div>
                <Date date={createdAt}/>
            </div>
        </div>}
        actions={<>
            {file?.url && <>
                <Button
                    startIcon={<DownloadIcon/>}
                    href={file.url}
                    target={'_blank'}
                    rel={'noreferrer'}
                >
                    {t('renditions.download', 'Download')}
                </Button>
                <SaveAsButton
                    asset={asset}
                    file={file}
                    variant={"outlined"}
                />
            </>}
        </>}
    />
}

function AssetFileVersionStructure({
    name,
    info,
    media,
    actions,
    maxDimensions,
}: {
    name: ReactNode;
    media: ReactNode | undefined;
    actions: ReactNode;
    maxDimensions: Dimensions;
    info: ReactNode;
}) {
    return <Card
        {...cardProps}
    >
        <CardMedia
            sx={theme => ({
                ...maxDimensions,
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                backgroundColor: theme.palette.grey["100"],
            })}
        >
            {media || ''}
        </CardMedia>
        <CardContent sx={cardContentSx}>
            <Typography component="div" variant="h5">
                {name}
            </Typography>
            <Typography component="div" variant="body1">
                {info}
            </Typography>

            <CardActions disableSpacing>
                {actions}
            </CardActions>
        </CardContent>
    </Card>
}

export function AssetFileVersionSkeleton({maxDimensions}: {
    maxDimensions: Dimensions;
}) {
    return <AssetFileVersionStructure
        name={<Skeleton variant={'text'}/>}
        info={<Skeleton variant={'text'} width={'50%'}/>}
        maxDimensions={maxDimensions}
        media={<Skeleton
            {...maxDimensions}
            variant={'rectangular'}
        />}
        actions={<Skeleton
            width={150}
            height={60}
            variant={'rectangular'}
        />}
    />
}
