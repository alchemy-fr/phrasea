import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Box,
    ListItem,
    ListItemIcon,
    ListItemSecondaryAction,
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

type Props = {
    asset: Asset;
} & BaseAttributeRowUIProps;

function AssetAttachments({asset}: Props) {
    const [expanded, setExpanded] = React.useState(false);
    const {t} = useTranslation();
    const {openModal} = useModals();

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
                    {asset.attachments?.map((a: AssetAttachment) => {
                        return (
                            <ListItem key={a.id} onClick={() => {}}>
                                <ListItemIcon>
                                    <AttachFileIcon />
                                </ListItemIcon>
                                <ListItemText
                                    primary={a.resolvedName || a.name}
                                />
                                {a.file.url ? (
                                    <ListItemSecondaryAction>
                                        <IconButton
                                            component={'a'}
                                            href={a.file.url!}
                                            target={'_blank'}
                                            rel={'noopener noreferrer'}
                                        >
                                            <DownloadIcon />
                                        </IconButton>
                                    </ListItemSecondaryAction>
                                ) : null}
                            </ListItem>
                        );
                    })}
                </MenuList>
                <Box>
                    <Button
                        onClick={() => {
                            openModal(AddAttachmentDialog, {asset});
                        }}
                        fullWidth={true}
                    >
                        {t('asset.view.add_attachment', 'Add Attachment')}
                    </Button>
                </Box>
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetAttachments);
