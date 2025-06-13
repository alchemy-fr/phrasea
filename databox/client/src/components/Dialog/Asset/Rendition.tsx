import React from 'react';
import {Asset, AssetRendition} from '../../../types';
import FilePlayer from '../../Media/Asset/FilePlayer';
import {Dimensions} from '../../Media/Asset/Players';
import {Box, Button, Chip, Tooltip} from '@mui/material';
import byteSize from 'byte-size';
import DownloadIcon from '@mui/icons-material/Download';
import SaveAsButton from '../../Media/Asset/Actions/SaveAsButton';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import {RenditionStructure} from './RenditionStructure.tsx';
import {LoadingButton} from '@mui/lab';
import LockIcon from '@mui/icons-material/Lock';
import AspectRatioIcon from '@mui/icons-material/AspectRatio';
import CropRotateIcon from '@mui/icons-material/CropRotate';
import ChangeCircleIcon from '@mui/icons-material/ChangeCircle';
import InfoIcon from '@mui/icons-material/Info';
import IconButton from '@mui/material/IconButton';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import {modalRoutes} from '../../../routes.ts';

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
    rendition: {nameTranslated, file, dirty, substituted, projection, locked},
    onDelete,
}: Props) {
    const {t} = useTranslation();
    const [deleting, setDeleting] = React.useState(false);
    const navigateToModal = useNavigateToModal();

    const deleteRendition = async () => {
        setDeleting(true);
        try {
            await onDelete();
        } finally {
            setDeleting(false);
        }
    };

    return (
        <RenditionStructure
            title={
                <Box
                    sx={{
                        'display': 'flex',
                        'flexDirection': 'row',
                        'alignItems': 'center',
                        '.MuiSvgIcon-root': {
                            ml: 1,
                            display: 'block',
                        },
                    }}
                >
                    <div>{nameTranslated}</div>
                    {locked && (
                        <Tooltip
                            title={t(
                                'rentition.flags.locked',
                                'Rendition is locked'
                            )}
                        >
                            <LockIcon />
                        </Tooltip>
                    )}
                    {substituted && (
                        <Tooltip
                            title={t(
                                'rentition.flags.substituted',
                                'Rendition has been substituted'
                            )}
                        >
                            <ChangeCircleIcon />
                        </Tooltip>
                    )}
                    {undefined !== projection && (
                        <>
                            {projection ? (
                                <Tooltip
                                    title={t(
                                        'rentition.flags.is_projection',
                                        'Rendition is a projection of the source'
                                    )}
                                >
                                    <AspectRatioIcon />
                                </Tooltip>
                            ) : (
                                <Tooltip
                                    title={t(
                                        'rentition.flags.non_projection',
                                        'Rendition is not a projection and has been alterated in its structure'
                                    )}
                                >
                                    <CropRotateIcon />
                                </Tooltip>
                            )}
                        </>
                    )}
                </Box>
            }
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
                        <IconButton
                            sx={{
                                ml: 1,
                            }}
                            onClick={() => {
                                navigateToModal(
                                    modalRoutes.files.routes.manage,
                                    {
                                        tab: 'metadata',
                                        id: file!.id,
                                    }
                                );
                            }}
                        >
                            <InfoIcon />
                        </IconButton>
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
                        startIcon={<DeleteIcon />}
                    >
                        {t('renditions.delete', 'Delete')}
                    </LoadingButton>
                </>
            }
        />
    );
}
