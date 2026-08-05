import {ApiFile, FileAnalysis} from '../../../types.ts';
import {Alert, Box} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {AnalysisStatus} from '../../Media/Asset/Quarantine/analysisTypes.ts';
import AnalyzerResults from '../../Media/Asset/AnalyzerResults.tsx';

type Props = {
    file: ApiFile;
};

export default function FileAnalysisReport({file}: Props) {
    const {t} = useTranslation();
    const analysis: FileAnalysis | null | undefined = file.analysis;

    return (
        <Box>
            {analysis?.status === AnalysisStatus.Skipped ? (
                <Alert severity={'info'} sx={{mb: 2}}>
                    {analysis.message ??
                        t(
                            'file_analyzer.result.status.skipped',
                            'File analysis was skipped.'
                        )}
                </Alert>
            ) : analysis?.status === AnalysisStatus.Success ? (
                <Alert severity={'error'} sx={{mb: 2}}>
                    {t(
                        'file_analyzer.result.status.success',
                        'This file was analyzed successfully'
                    )}
                </Alert>
            ) : (
                <Alert severity={'error'} sx={{mb: 2}}>
                    {t(
                        'file_analyzer.result.status.rejected_with_reason',
                        'This file was rejected by the following analyzers.'
                    )}
                </Alert>
            )}

            <AnalyzerResults file={file} />
        </Box>
    );
}
