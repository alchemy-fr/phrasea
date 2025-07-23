import React from 'react';
import {Asset, AssetRendition} from '../../../../types.ts';
import FilePlayer from '../../../Media/Asset/FilePlayer.tsx';
import {Dimensions} from '../../../Media/Asset/Players';
import {
    Box,
    Chip,
    Divider,
    ListItemIcon,
    ListItemText,
    MenuItem,
    Tooltip,
} from '@mui/material';
import byteSize from 'byte-size';
import SaveAsButton from '../../../Media/Asset/Actions/SaveAsButton.tsx';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import {RenditionStructure} from './RenditionStructure.tsx';
import LockIcon from '@mui/icons-material/Lock';
import AspectRatioIcon from '@mui/icons-material/AspectRatio';
import CropRotateIcon from '@mui/icons-material/CropRotate';
import ChangeCircleIcon from '@mui/icons-material/ChangeCircle';
import InfoIcon from '@mui/icons-material/Info';
import IconButton from '@mui/material/IconButton';
import {useNavigateToModal} from '../../../Routing/ModalLink.tsx';
import {MoreActionsButton} from '@alchemy/phrasea-ui';
import {modalRoutes} from '../../../../routes.ts';
import UploadIcon from '@mui/icons-material/Upload';
import DownloadIcon from '@mui/icons-material/Download';
import SaveIcon from '@mui/icons-material/Save';

type Props = {
    asset: Asset;
    title: string | undefined;
    rendition: AssetRendition;
    dimensions: Dimensions;
    onDelete: () => Promise<void>;
    onUpload: (rendition: AssetRendition) => void;
};

export function Rendition({
    title,
    asset,
    dimensions,
    rendition,
    onDelete,
    onUpload,
}: Props) {
    const {t} = useTranslation();
    const [deleting, setDeleting] = React.useState(false);
    const navigateToModal = useNavigateToModal();
    const {nameTranslated, file, dirty, substituted, projection, locked} =
        rendition;

    const deleteRendition = async () => {
        setDeleting(true);
        try {
            await onDelete();
        } finally {
            setDeleting(false);
        }
    };

    const uploadRendition = () => {
        onUpload(rendition);
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
                <MoreActionsButton
                    disablePortal={false}
                    anchorOrigin={{
                        vertical: 'bottom',
                        horizontal: 'left',
                    }}
                >
                    {closeWrapper => {
                        const actions = [];

                        if (file) {
                            actions.push(
                                <MenuItem
                                    component="a"
                                    key={'download'}
                                    href={file.url}
                                    target={'_blank'}
                                    rel={'noreferrer'}
                                >
                                    <ListItemIcon>
                                        <DownloadIcon />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t(
                                            'renditions.download',
                                            'Download'
                                        )}
                                    />
                                </MenuItem>
                            );
                            actions.push(
                                <MenuItem
                                    key={'replace'}
                                    onClick={closeWrapper(uploadRendition)}
                                >
                                    <ListItemIcon>
                                        <UploadIcon />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t(
                                            'renditions.replace',
                                            'Replace'
                                        )}
                                    />
                                </MenuItem>
                            );
                            actions.push(
                                <SaveAsButton
                                    key={'save-as'}
                                    asset={asset}
                                    file={file}
                                    icon={<SaveIcon />}
                                    variant={'outlined'}
                                    closeWrapper={closeWrapper}
                                    Component={MenuItem}
                                />
                            );
                        } else {
                            actions.push(
                                <MenuItem
                                    key={'upload'}
                                    onClick={closeWrapper(uploadRendition)}
                                >
                                    <ListItemIcon>
                                        <UploadIcon />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t(
                                            'renditions.upload',
                                            'Upload'
                                        )}
                                    />
                                </MenuItem>
                            );
                        }

                        actions.push(<Divider key={'div1'} />);
                        actions.push(
                            <MenuItem
                                key={'delete'}
                                onClick={closeWrapper(deleteRendition)}
                                disabled={deleting}
                                color={'error'}
                            >
                                <ListItemIcon>
                                    <DeleteIcon />
                                </ListItemIcon>
                                <ListItemText
                                    primary={t('renditions.delete', 'Delete')}
                                />
                            </MenuItem>
                        );

                        return actions;
                    }}
                </MoreActionsButton>
            }
        />
    );
}
