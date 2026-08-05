import {ApiFile} from '../../../types.ts';
import {Stack} from '@mui/material';
import {FileAnalysis} from './Quarantine/analysisTypes.ts';
import AnalyzerResult from './Quarantine/AnalyzerResult.tsx';

type Props = {
    file: ApiFile;
};

export default function AnalyzerResults({file}: Props) {
    const analysis: FileAnalysis | null | undefined = file.analysis;
    const results = analysis?.results ?? [];

    return (
        <>
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
        </>
    );
}
