import {Box, Chip, Stack, Typography} from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import {useTranslation} from 'react-i18next';

type Props = {
    duplicates: string[] | undefined;
};

/**
 * Lists the file IDs that were detected as duplicates of the analyzed file.
 * Shared by the checksum and doc_unique_id analyzers.
 */
export default function DuplicateList({duplicates}: Props) {
    const {t} = useTranslation();

    if (!duplicates || duplicates.length === 0) {
        return null;
    }

    return (
        <Box sx={{mt: 1}}>
            <Typography
                variant={'subtitle2'}
                sx={{display: 'flex', alignItems: 'center', gap: 0.5, mb: 1}}
            >
                <ContentCopyIcon fontSize={'small'} />
                {t('quarantine.duplicates.title', {
                    defaultValue: '{{count}} duplicate file(s) found',
                    count: duplicates.length,
                })}
            </Typography>
            <Stack
                direction={'row'}
                spacing={1}
                useFlexGap
                sx={{flexWrap: 'wrap'}}
            >
                {duplicates.map(id => (
                    <Chip
                        key={id}
                        size={'small'}
                        variant={'outlined'}
                        label={id}
                    />
                ))}
            </Stack>
        </Box>
    );
}
