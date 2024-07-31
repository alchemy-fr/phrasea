import {useCallback, useContext, useState} from 'react';
import {createStrictDimensions, PlayerProps} from './index';
import {Document, Page, pdfjs} from 'react-pdf';
import {getRatioDimensions} from './VideoPlayer';
import {DisplayContext} from '../../DisplayContext';
import 'react-pdf/dist/esm/Page/TextLayer.css';
import 'react-pdf/dist/esm/Page/AnnotationLayer.css';

type Props = {} & PlayerProps;

export default function PDFPlayer({
    file,
    dimensions: forcedDimensions,
    onLoad,
}: Props) {
    const [ratio, setRatio] = useState<number>();
    const displayContext = useContext(DisplayContext);
    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: displayContext!.thumbSize}
    );
    const pdfDimensions = getRatioDimensions(dimensions, ratio);
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
                maxWidth: dimensions.width,
                maxHeight: dimensions.height,
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

pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.js`;
