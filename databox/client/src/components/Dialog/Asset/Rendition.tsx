import React from 'react';
import {Asset, AssetRendition} from '../../../types';
import FilePlayer from '../../Media/Asset/FilePlayer';
import {Dimensions} from '../../Media/Asset/Players';
import {Button, Chip,} from '@mui/material';
import byteSize from 'byte-size';
import DownloadIcon from '@mui/icons-material/Download';
import SaveAsButton from '../../Media/Asset/Actions/SaveAsButton';
import {useTranslation} from 'react-i18next';
import DeleteIcon from "@mui/icons-material/Delete";
import {RenditionStructure} from "./RenditionStructure.tsx";
import {LoadingButton} from "@mui/lab";

type Props = {
    asset: Asset;
    title: string | undefined;
    rendition: AssetRendition;
    dimensions: Dimensions;
    onDelete: () => Promise<void>;
};

export function Rendition({
    title,
    asset,
    dimensions,
    rendition: {name, file, dirty},
    onDelete,
}: Props) {
    const {t} = useTranslation();
    const [deleting, setDeleting] = React.useState(false);

    const deleteRendition = async () => {
        setDeleting(true);
        try {
            await onDelete();
        } finally {
            setDeleting(false);
        }
    }

    return (
        <RenditionStructure
            title={name}
            dimensions={dimensions}
            media={
                file ? (
                    <FilePlayer
                        file={file}
                        title={title}
                        dimensions={dimensions}
                        autoPlayable={false}
                        controls={true}
                    />
                ) : undefined
            }
            info={
                file && (
                    <div>
                        {file.size ? (
                            <>{byteSize(file.size).toString()} • </>
                        ) : (
                            ''
                        )}
                        {file.type ? file.type : ''}
                        {dirty ? (
                            <>
                                {` • `}
                                <Chip
                                    size={'small'}
                                    color={'error'}
                                    label={t('renditions.dirty', 'Dirty')}
                                />
                            </>
                        ) : (
                            ''
                        )}
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
                    <LoadingButton
                        loading={deleting}
                        disabled={deleting}
                        onClick={deleteRendition}
                        color={'error'}
                        startIcon={<DeleteIcon/>}
                    >
                        {t('renditions.delete', 'Delete')}
                    </LoadingButton>
                </>
            }
        />
    );
}

