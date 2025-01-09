import React, {useCallback, useContext, useEffect, useMemo, useRef, useState} from 'react';
import {createStrictDimensions, PlayerProps} from './index';
import {Document, Page, pdfjs} from 'react-pdf';
import {getRatioDimensions} from './VideoPlayer';
import {DisplayContext} from '../../DisplayContext';
import 'react-pdf/dist/esm/Page/TextLayer.css';
import 'react-pdf/dist/esm/Page/AnnotationLayer.css';
import {CircularProgress, IconButton} from '@mui/material';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import {AssetAnnotationHandle} from "../Annotations/AssetAnnotationsOverlay.tsx";
import {AssetAnnotation} from "../Annotations/annotationTypes.ts";
import FileToolbar from "./FileToolbar.tsx";

type Props = {
    controls?: boolean | undefined;
} & PlayerProps;

export default function PDFPlayer({
    file,
    controls,
    dimensions: forcedDimensions,
    onLoad,
    annotations,
    onNewAnnotation,
    zoomEnabled,
}: Props) {
    const [ratio, setRatio] = useState<number>();
    const [numPages, setNumPages] = useState<number>();
    const [pageNumber, setPageNumber] = useState<number>(1);
    const [renderedPageNumber, setRenderedPageNumber] = React.useState<number | undefined>();
    const displayContext = useContext(DisplayContext);
    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: displayContext!.thumbSize}
    );
    const annotationsOverlayRef = useRef<AssetAnnotationHandle | null>(null);
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
            goTo && setPageNumber(goTo);
        }
    }, [annotations]);

    const pageAnnotations: AssetAnnotation[] = useMemo(() => annotations?.filter(a => a.page === pageNumber) ?? [], [annotations, pageNumber]);

    return (
        <FileToolbar
            controls={controls}
            onNewAnnotation={onNewAnnotation}
            annotations={renderedPageNumber === pageNumber && pageAnnotations.length > 0 ? pageAnnotations : undefined}
            zoomEnabled={zoomEnabled}
            annotationEnabled={true}
            page={pageNumber}
            preToolbarActions={controls ? <>
                <div>
                    <IconButton
                        onClick={() =>
                            setPageNumber(pageNumber - 1)
                        }
                        disabled={pageNumber === 1}
                    >
                        <KeyboardArrowLeftIcon/>
                    </IconButton>
                </div>
                <div style={{
                    whiteSpace: 'nowrap',
                }}>
                    {pageNumber} / {numPages}
                </div>
                <div>
                    <IconButton
                        onClick={() =>
                            setPageNumber(pageNumber + 1)
                        }
                        disabled={pageNumber === numPages}
                    >
                        <KeyboardArrowRightIcon/>
                    </IconButton>
                </div>
            </> : undefined
            }
        >
            <div
                style={{
                    maxWidth: dimensions.width,
                    maxHeight: dimensions.height,
                    position: 'relative',
                    backgroundColor: '#FFF',
                }}
            >
                <Document file={file.url} onLoadSuccess={onDocLoad}>
                    {ratio ? (
                        <>

                            <Page
                                {...pdfDimensions}
                                key={pageNumber}
                                pageNumber={pageNumber}
                                loading={<div
                                    style={{
                                        position: 'absolute',
                                        top: 0,
                                        left: 0,
                                        display: 'flex',
                                        justifyContent: 'center',
                                        alignItems: 'center',
                                        height: '100%',
                                        width: '100%',
                                    }}
                                >
                                    <CircularProgress/>
                                </div>}
                                onRenderSuccess={() => {
                                    setRenderedPageNumber(pageNumber);
                                    annotationsOverlayRef.current?.render();
                                }}
                            />
                        </>
                    ) : null}
                </Document>
            </div>
        </FileToolbar>
    );
}

pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${
    pdfjs.version
}/build/pdf.worker.min.mjs`;
