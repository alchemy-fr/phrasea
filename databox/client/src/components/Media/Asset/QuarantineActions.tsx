import {Asset} from '../../../types.ts';
import {Alert, Box, Button, Stack, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import ApprovalIcon from '@mui/icons-material/Approval';
import {AnalysisStatus, FileAnalysis} from './Quarantine/analysisTypes.ts';
import AnalyzerResult from './Quarantine/AnalyzerResult.tsx';

type Props = {
    asset: Asset;
};

export default function QuarantineActions({asset}: Props) {
    const {t} = useTranslation();
    const analysis: FileAnalysis | null | undefined = asset.source?.analysis;
    const results = analysis?.results ?? [];

    return (
        <Box>
            <Typography variant={'h6'} gutterBottom>
                {t('quarantine.title', 'Quarantined asset')}
            </Typography>

            {analysis?.status === AnalysisStatus.Skipped ? (
                <Alert severity={'info'} sx={{mb: 2}}>
                    {analysis.message ??
                        t('quarantine.skipped', 'File analysis was skipped.')}
                </Alert>
            ) : (
                <Alert severity={'error'} sx={{mb: 2}}>
                    {t(
                        'quarantine.rejected',
                        'This asset was rejected by the following file analyzers. Review the reasons below and choose an action.'
                    )}
                </Alert>
            )}

            {results.length > 0 ? (
                <Stack spacing={2} sx={{mb: 2}}>
                    {results.map((result, index) => (
                        <AnalyzerResult
                            key={`${result.name}-${index}`}
                            result={result}
                        />
                    ))}
                </Stack>
            ) : null}

            <Stack direction={'row'} gap={1} sx={{flexWrap: 'wrap'}}>
                <Button
                    variant={'contained'}
                    color={'error'}
                    startIcon={<ApprovalIcon />}
                >
                    {t('quarantine.actions.bypass', 'By pass (accept)')}
                </Button>
                <Button variant={'outlined'} startIcon={<DeleteIcon />}>
                    {t('quarantine.actions.move_to_trash', 'Move to trash')}
                </Button>
            </Stack>
        </Box>
    );
}
