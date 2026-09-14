import {ApiFile} from '../../../types.ts';
import {Alert, Box} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {getFileAnalysisPresentation} from '../../Media/Asset/Quarantine/analysisPresentation.ts';
import AnalyzerResults from '../../Media/Asset/AnalyzerResults.tsx';

type Props = {
    file: ApiFile;
};

export default function FileAnalysisReport({file}: Props) {
    const {t} = useTranslation();
    const {severity, message, showAlert, showReport} =
        getFileAnalysisPresentation(file, t);

    // Nothing to add to the status chip shown next to it.
    if (!showAlert && !showReport) {
        return null;
    }

    return (
        <Box>
            {showAlert ? (
                <Alert severity={severity} sx={{mb: 2}}>
                    {message}
                </Alert>
            ) : null}

            {showReport ? <AnalyzerResults file={file} /> : null}
        </Box>
    );
}
