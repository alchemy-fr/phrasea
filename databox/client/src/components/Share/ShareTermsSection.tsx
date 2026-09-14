import {Box, Button, Paper, Typography, useTheme} from '@mui/material';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import {useTranslation} from 'react-i18next';
import {ShareTerms} from '../../types.ts';

type Props = {
    terms: ShareTerms;
};

export default function ShareTermsSection({terms}: Props) {
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
                {t('share.terms.title', 'Terms & Conditions')}
            </Typography>
            <Typography
                variant="body2"
                sx={{
                    color: 'text.secondary',
                    mb: 2,
                }}
            >
                {t(
                    'share.terms.subtitle',
                    '{{workspace}} — version {{version}}',
                    {
                        workspace: terms.workspaceName,
                        version: terms.version,
                    }
                )}
            </Typography>
            {terms.pdfUrl ? (
                <Button
                    variant={'outlined'}
                    href={terms.pdfUrl}
                    target={'_blank'}
                    rel={'noreferrer'}
                    startIcon={<PictureAsPdfIcon />}
                >
                    {t('share.terms.view_pdf', 'View Terms & Conditions (PDF)')}
                </Button>
            ) : (
                <Box
                    component={'div'}
                    sx={{
                        whiteSpace: 'pre-wrap',
                    }}
                >
                    {terms.text}
                </Box>
            )}
        </Paper>
    );
}
