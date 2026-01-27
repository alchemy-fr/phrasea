import {useCallback, useContext, useEffect, useMemo, useState} from 'react';
import {PlayerProps} from './index';
import {createStrictDimensions, getRatioDimensions} from '@alchemy/core';
import {pdfjs} from 'react-pdf';
import {DisplayContext} from '../../DisplayContext';
import 'react-pdf/dist/esm/Page/TextLayer.css';
import 'react-pdf/dist/esm/Page/AnnotationLayer.css';
import {IconButton} from '@mui/material';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import {AssetAnnotation} from '../Annotations/annotationTypes.ts';
import FileToolbar from './FileToolbar.tsx';
import {PdfView, useTracking} from '@alchemy/phrasea-framework';

type Props = {
    controls?: boolean | undefined;
} & PlayerProps;

export default function PDFPlayer({
    file,
    controls,
    dimensions: forcedDimensions,
    onLoad,
    annotations,
    trackingId,
    title,
    ...playerProps
}: Props) {
    const [ratio, setRatio] = useState<number>();
    const [numPages, setNumPages] = useState<number>();
    const [pageNumber, setPageNumber] = useState<number>(1);
    const displayContext = useContext(DisplayContext);
    const d = displayContext?.state;
    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: d?.thumbSize ?? 200}
    );
    const {trackContentInteraction} = useTracking();
    const pdfDimensions = getRatioDimensions(dimensions, ratio);
    const onDocLoad = useCallback(
        (pdf: any) => {
            setNumPages(pdf.numPages);
            pdf.getPage(1).then((page: any) => {
                setRatio(page.view[3] / page.view[2]);
            });

            onLoad && onLoad();
        },
        [onLoad]
    );

    useEffect(() => {
        if (annotations && annotations.length > 0) {
            const goTo = annotations[annotations.length - 1].page;
            numPages &&
                goTo &&
                goTo > 0 &&
                goTo <= numPages &&
                setPageNumber(goTo);
        }
    }, [annotations, numPages]);

    const pageAnnotations: AssetAnnotation[] = useMemo(
        () => annotations?.filter(a => a.page === pageNumber) ?? [],
        [annotations, pageNumber]
    );

    return (
        <FileToolbar
            {...playerProps}
            key={file.id}
            controls={controls}
            annotations={pageAnnotations}
            annotationEnabled={true}
            page={pageNumber}
            preToolbarActions={
                controls ? (
                    <>
                        <div>
                            <IconButton
                                onClick={() => setPageNumber(pageNumber - 1)}
                                disabled={pageNumber === 1}
                            >
                                <KeyboardArrowLeftIcon />
                            </IconButton>
                        </div>
                        <div
                            style={{
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {pageNumber} / {numPages}
                        </div>
                        <div>
                            <IconButton
                                onClick={() => setPageNumber(pageNumber + 1)}
                                disabled={pageNumber === numPages}
                            >
                                <KeyboardArrowRightIcon />
                            </IconButton>
                        </div>
                    </>
                ) : undefined
            }
        >
            {({zoomStep, transformWrapperRef}) => (
                <div
                    style={{
                        maxWidth: dimensions.width,
                        maxHeight: dimensions.height,
                        position: 'relative',
                        backgroundColor: '#FFF',
                    }}
                    onClick={
                        trackingId
                            ? () => {
                                  trackContentInteraction(
                                      trackingId,
                                      title,
                                      'click'
                                  );
                              }
                            : undefined
                    }
                >
                    <PdfView
                        file={file.url}
                        onLoadSuccess={onDocLoad}
                        ratio={ratio}
                        pdfDimensions={pdfDimensions}
                        pageNumber={pageNumber}
                        zoomStep={zoomStep}
                        onRenderSuccess={() => {
                            transformWrapperRef.current?.centerView();
                        }}
                    />
                </div>
            )}
        </FileToolbar>
    );
}

pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${
    pdfjs.version
}/build/pdf.worker.min.mjs`;
