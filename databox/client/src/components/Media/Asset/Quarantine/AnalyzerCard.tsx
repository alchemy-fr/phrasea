import {Box, Chip, Paper, Stack, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutline';
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutline';
import WarningAmberIcon from '@mui/icons-material/WarningAmber';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import {AlertColor} from '@mui/material';
import {ReactNode} from 'react';

type Props = {
    title: ReactNode;
    severity: AlertColor;
    children: ReactNode;
};

const severityIcon: Record<AlertColor, ReactNode> = {
    error: <ErrorOutlineIcon color={'error'} />,
    warning: <WarningAmberIcon color={'warning'} />,
    info: <InfoOutlinedIcon color={'info'} />,
    success: <CheckCircleOutlineIcon color={'success'} />,
};

/**
 * Titled container rendered once per analyzer result. Displays the analyzer
 * name, a status icon derived from the highest severity it reported, and the
 * analyzer-specific content below.
 */
export default function AnalyzerCard({title, severity, children}: Props) {
    const {t} = useTranslation();

    const severityLabel: Record<AlertColor, string> = {
        error: t('analysis.severity.error', 'Error'),
        warning: t('analysis.severity.warning', 'Warning'),
        info: t('analysis.severity.info', 'Info'),
        success: t('analysis.severity.success', 'OK'),
    };

    return (
        <Paper variant={'outlined'} sx={{p: 2}}>
            <Stack
                direction={'row'}
                spacing={1}
                alignItems={'center'}
                sx={{mb: 1}}
            >
                {severityIcon[severity]}
                <Typography variant={'subtitle1'} sx={{fontWeight: 600}}>
                    {title}
                </Typography>
                <Box sx={{flexGrow: 1}} />

                <Chip
                    size={'small'}
                    color={severity}
                    label={severityLabel[severity]}
                />
            </Stack>
            {children}
        </Paper>
    );
}
