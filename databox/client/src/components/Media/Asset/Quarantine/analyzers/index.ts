import {AnalyzerComponent} from './types.ts';
import ChecksumResult from './ChecksumResult.tsx';
import DocUniqueIdResult from './DocUniqueIdResult.tsx';
import FilenameResult from './FilenameResult.tsx';
import ImageColorspaceResult from './ImageColorspaceResult.tsx';
import ImageDimensionResult from './ImageDimensionResult.tsx';
import GenericResult from './GenericResult.tsx';

/**
 * Maps an analyzer name (`AnalyzerInterface::getName()`) to the React component
 * that renders its output. Unknown analyzers fall back to {@link GenericResult}.
 */
export const analyzerComponents: Record<string, AnalyzerComponent> = {
    checksum: ChecksumResult,
    doc_unique_id: DocUniqueIdResult,
    filename: FilenameResult,
    image_colorspace: ImageColorspaceResult,
    image_dimension: ImageDimensionResult,
    debug: GenericResult,
};

export function getAnalyzerComponent(name: string): AnalyzerComponent {
    return analyzerComponents[name] ?? GenericResult;
}
