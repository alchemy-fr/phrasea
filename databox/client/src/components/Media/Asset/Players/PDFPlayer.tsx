import {useCallback, useState} from 'react';
import {PlayerProps} from './index';
import {Document, Page} from 'react-pdf';
import {getMaxVideoDimensions} from './VideoPlayer';

type Props = {} & PlayerProps;

export default function PDFPlayer({
    file,
    minDimensions,
    maxDimensions,
    onLoad,
}: Props) {
    const [ratio, setRatio] = useState<number>();
    const pdfDimensions = getMaxVideoDimensions(maxDimensions, ratio);
    const onDocLoad = useCallback(
        (pdf: any) => {
            pdf.getPage(1).then((page: any) => {
                setRatio(page.view[3] / page.view[2]);
            });

            onLoad && onLoad();
        },
        [onLoad]
    );

    return (
        <div
            style={{
                maxWidth: maxDimensions.width,
                maxHeight: maxDimensions.height,
                minWidth: minDimensions?.width,
                minHeight: minDimensions?.height,
                position: 'relative',
                backgroundColor: '#FFF',
            }}
        >
            <Document file={file.url} onLoadSuccess={onDocLoad}>
                {ratio && (
                    <Page
                        {...pdfDimensions}
                        pageNumber={1}
                        onLoadSuccess={onLoad}
                    />
                )}
            </Document>
        </div>
    );
}
