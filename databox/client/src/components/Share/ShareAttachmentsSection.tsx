import {
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    Paper,
    Typography,
    useTheme,
} from '@mui/material';
import AttachFileIcon from '@mui/icons-material/AttachFile';
import {useTranslation} from 'react-i18next';
import {ShareAttachment} from '../../types.ts';
import {formatFilesize} from '../../lib/filesizeFormatter.ts';

type Props = {
    attachments: ShareAttachment[];
};

export default function ShareAttachmentsSection({attachments}: Props) {
    const {t, i18n} = useTranslation();
    const theme = useTheme();
    const width = theme.breakpoints.values.md;

    return (
        <Paper
            elevation={1}
            sx={{
                maxWidth: width,
                margin: '0 auto',
                mb: 3,
                p: 3,
            }}
        >
            <Typography variant="h2">
                {t('share.attachments.title', 'Attached Files')}
            </Typography>
            <List>
                {attachments.map(attachment => (
                    <ListItemButton
                        key={attachment.id}
                        component={'a'}
                        href={attachment.url}
                        target={'_blank'}
                        rel={'noreferrer'}
                    >
                        <ListItemIcon>
                            <AttachFileIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={
                                attachment.name ||
                                t('share.attachments.file', 'File')
                            }
                            secondary={
                                attachment.size
                                    ? formatFilesize(
                                          t,
                                          attachment.size,
                                          true,
                                          i18n.language
                                      )
                                    : undefined
                            }
                        />
                    </ListItemButton>
                ))}
            </List>
        </Paper>
    );
}
