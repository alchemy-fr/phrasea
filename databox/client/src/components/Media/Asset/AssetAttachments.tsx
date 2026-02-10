import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Box,
    Divider,
    ListItem,
    ListItemIcon,
    ListItemText,
    MenuList,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import React, {memo} from 'react';
import {Asset, AssetAttachment} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {BaseAttributeRowUIProps} from './Attribute/AttributeRowUI.tsx';
import {thumbSx} from './AssetThumb.tsx';
import AttachFileIcon from '@mui/icons-material/AttachFile';
import IconButton from '@mui/material/IconButton';
import DownloadIcon from '@mui/icons-material/Download';
import {useModals} from '@alchemy/navigation';
import Button from '@mui/material/Button';
import AddAttachmentDialog from './Actions/AddAttachmentDialog.tsx';
import {DropdownActions} from '@alchemy/phrasea-ui';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import DeleteIcon from '@mui/icons-material/Delete';
import {deleteAttachment} from '../../../api/attachment.ts';
import MenuItem from '@mui/material/MenuItem';
import {ConfirmDialog} from '@alchemy/phrasea-framework';
import EditIcon from '@mui/icons-material/Edit';
import RenameAttachmentDialog from './Attachment/RenameAttachmentDialog.tsx';
import FileAnalysisChip from './FileAnalysisChip.tsx';
import {deleteAsset} from '../../../api/asset.ts';
import LinkOffIcon from '@mui/icons-material/LinkOff';
import {toast} from 'react-toastify';

type Props = {
    asset: Asset;
} & BaseAttributeRowUIProps;

function AssetAttachments({asset}: Props) {
    const [expanded, setExpanded] = React.useState(false);
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [attachments, setAttachments] = React.useState(asset.attachments);

    React.useEffect(() => {
        setAttachments(asset.attachments);
    }, [asset]);

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography component="div">
                    {t('asset.view.attachments', `Attachments`)}
                </Typography>
            </AccordionSummary>
            <AccordionDetails
                sx={{
                    p: 0,
                }}
            >
                <MenuList
                    sx={theme => thumbSx(50, theme)}
                    disablePadding={true}
                >
                    {attachments?.map((attachment: AssetAttachment) => {
                        const file = attachment.attachment.source!;

                        return (
                            <ListItem
                                key={attachment.id}
                                secondaryAction={
                                    <>
                                        {file.url ? (
                                            <IconButton
                                                component={'a'}
                                                href={file.url!}
                                                target={'_blank'}
                                                rel={'noopener noreferrer'}
                                            >
                                                <DownloadIcon />
                                            </IconButton>
                                        ) : null}
                                        <DropdownActions
                                            disablePortal={false}
                                            mainButton={({...props}) => (
                                                <IconButton {...props}>
                                                    <MoreVertIcon />
                                                </IconButton>
                                            )}
                                        >
                                            {closeMenu => [
                                                <MenuItem
                                                    key={'rename'}
                                                    onClick={closeMenu(() => {
                                                        openModal(
                                                            RenameAttachmentDialog,
                                                            {
                                                                attachment,
                                                                onEdit: (
                                                                    editedAttachment: AssetAttachment
                                                                ) => {
                                                                    setAttachments(
                                                                        prev =>
                                                                            prev.map(
                                                                                att =>
                                                                                    att.id ===
                                                                                    editedAttachment.id
                                                                                        ? editedAttachment
                                                                                        : att
                                                                            )
                                                                    );
                                                                },
                                                            }
                                                        );
                                                    })}
                                                >
                                                    <ListItemIcon>
                                                        <EditIcon />
                                                    </ListItemIcon>
                                                    <ListItemText
                                                        primary={t(
                                                            'asset.view.rename_attachment',
                                                            'Rename Attachment'
                                                        )}
                                                    />
                                                </MenuItem>,
                                                <Divider key={'divider1'} />,
                                                <MenuItem
                                                    key={'detach'}
                                                    onClick={closeMenu(() => {
                                                        openModal(
                                                            ConfirmDialog,
                                                            {
                                                                title: t(
                                                                    'asset.view.detach_attachment.confirm_title',
                                                                    'Detach from Asset'
                                                                ),
                                                                children: t(
                                                                    'asset.view.detach_attachment.confirm_message',
                                                                    'Are you sure you want to detach this from the asset?'
                                                                ),
                                                                confirmLabel: t(
                                                                    'asset.view.detach_attachment.confirm_button',
                                                                    'Detach'
                                                                ),
                                                                confirmButtonProps:
                                                                    {
                                                                        startIcon:
                                                                            (
                                                                                <LinkOffIcon />
                                                                            ),
                                                                    },
                                                                onConfirm:
                                                                    async () => {
                                                                        setAttachments(
                                                                            prev =>
                                                                                prev.filter(
                                                                                    att =>
                                                                                        att.id !==
                                                                                        attachment.id
                                                                                )
                                                                        );
                                                                        deleteAttachment(
                                                                            attachment.id
                                                                        );
                                                                        toast.success(
                                                                            t(
                                                                                'asset.view.detach_attachment.success_toast',
                                                                                'Attachment detached successfully'
                                                                            )
                                                                        );
                                                                    },
                                                            }
                                                        );
                                                    })}
                                                >
                                                    <ListItemIcon>
                                                        <LinkOffIcon />
                                                    </ListItemIcon>
                                                    <ListItemText
                                                        primary={t(
                                                            'asset.view.detach_attachment.button',
                                                            'Detach from Asset'
                                                        )}
                                                    />
                                                </MenuItem>,
                                                <MenuItem
                                                    key={'delete'}
                                                    onClick={closeMenu(() => {
                                                        openModal(
                                                            ConfirmDialog,
                                                            {
                                                                title: t(
                                                                    'asset.view.delete_attachment.confirm_title',
                                                                    'Delete Attachment'
                                                                ),
                                                                children: t(
                                                                    'asset.view.delete_attachment.confirm_message',
                                                                    'Are you sure you want to delete this attachment? This action cannot be undone.'
                                                                ),
                                                                confirmLabel: t(
                                                                    'asset.view.delete_attachment.confirm_button',
                                                                    'Delete'
                                                                ),
                                                                confirmButtonProps:
                                                                    {
                                                                        color: 'error',
                                                                        startIcon:
                                                                            (
                                                                                <DeleteIcon />
                                                                            ),
                                                                    },
                                                                onConfirm:
                                                                    async () => {
                                                                        setAttachments(
                                                                            prev =>
                                                                                prev.filter(
                                                                                    att =>
                                                                                        att.id !==
                                                                                        attachment.id
                                                                                )
                                                                        );
                                                                        deleteAsset(
                                                                            attachment
                                                                                .attachment
                                                                                .id
                                                                        );
                                                                        toast.success(
                                                                            t(
                                                                                'asset.view.delete_attachment.success_toast',
                                                                                'Attachment deleted successfully'
                                                                            )
                                                                        );
                                                                    },
                                                            }
                                                        );
                                                    })}
                                                >
                                                    <ListItemIcon>
                                                        <DeleteIcon />
                                                    </ListItemIcon>
                                                    <ListItemText
                                                        primary={t(
                                                            'asset.view.delete_attachment.button',
                                                            'Delete Attachment'
                                                        )}
                                                    />
                                                </MenuItem>,
                                            ]}
                                        </DropdownActions>
                                    </>
                                }
                            >
                                <ListItemIcon>
                                    <AttachFileIcon />
                                </ListItemIcon>
                                <ListItemText
                                    primary={
                                        attachment.name ||
                                        attachment.attachment.resolvedTitle
                                    }
                                    secondary={
                                        !file.accepted ? (
                                            <FileAnalysisChip file={file} />
                                        ) : undefined
                                    }
                                />
                            </ListItem>
                        );
                    })}
                </MenuList>
                <Box p={1} textAlign={'center'}>
                    <Button
                        onClick={() => {
                            openModal(AddAttachmentDialog, {
                                asset,
                                onAttachmentAdded: (
                                    attachment: AssetAttachment
                                ) => {
                                    setAttachments(prev => [
                                        ...prev,
                                        attachment,
                                    ]);
                                },
                            });
                        }}
                        variant="contained"
                        startIcon={<AttachFileIcon />}
                    >
                        {t('asset.view.add_attachment', 'Add Attachment')}
                    </Button>
                </Box>
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetAttachments);
