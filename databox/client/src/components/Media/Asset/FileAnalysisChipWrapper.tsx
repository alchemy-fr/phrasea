import React, {PropsWithChildren} from 'react';
import FileAnalysisChip, {FileAnalysisChipProps} from './FileAnalysisChip.tsx';

type Props = PropsWithChildren<FileAnalysisChipProps>;

export default function FileAnalysisChipWrapper({file, children}: Props) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexDirection: 'column',
                gap: '8px',
            }}
        >
            <FileAnalysisChip file={file} />

            {children}
        </div>
    );
}
