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

type Props = {
    attachments: ShareAttachment[];
};

function formatSize(size: number | null): string | undefined {
    if (!size) {
        return undefined;
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = size;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        ++unit;
    }

    return `${value.toFixed(unit > 0 ? 1 : 0)} ${units[unit]}`;
}

export default function ShareAttachmentsSection({attachments}: Props) {
    const {t} = useTranslation();
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
                            secondary={formatSize(attachment.size)}
                        />
                    </ListItemButton>
                ))}
            </List>
        </Paper>
    );
}
