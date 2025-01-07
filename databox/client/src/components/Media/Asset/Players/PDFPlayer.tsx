import {useCallback, useContext, useEffect, useRef, useState} from 'react';
import {createStrictDimensions, PlayerProps} from './index';
import {Document, Page, pdfjs} from 'react-pdf';
import {getRatioDimensions} from './VideoPlayer';
import {DisplayContext} from '../../DisplayContext';
import 'react-pdf/dist/esm/Page/TextLayer.css';
import 'react-pdf/dist/esm/Page/AnnotationLayer.css';
import {Box, CircularProgress, IconButton, Stack} from '@mui/material';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import AssetAnnotationsOverlay, {annotationZIndex} from "../Annotations/AssetAnnotationsOverlay.tsx";
import {AssetAnnotation} from "../../../../types.ts";
import AnnotateTool from "../Annotations/AnnotateTool.tsx";

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
}: Props) {
    const [ratio, setRatio] = useState<number>();
    const [numPages, setNumPages] = useState<number>();
    const pageRef = useRef<number>(1);
    const [pageNumber, setPageNumberProxy] = useState<number>(1);
    const [renderedPageNumber, setRenderedPageNumber] = useState<number>();
    const displayContext = useContext(DisplayContext);
    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: displayContext!.thumbSize}
    );
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

    const setPageNumber = (num: number): void => {
        pageRef.current = num;
        setPageNumberProxy(num);
    };

    const prevPageClassName = 'pdf-prev-page';
    const controlsClassName = 'pdf-controls';
    const isLoading = renderedPageNumber !== pageNumber;

    useEffect(() => {
        if (annotations && annotations.length > 0) {
            annotations[0].page && setPageNumber(annotations[0].page);
        }
    }, [annotations]);

    const pageAnnotations: AssetAnnotation[] = annotations?.filter(a => a.page === pageNumber) ?? [];

    return (
        <Box
            sx={{
                maxWidth: dimensions.width,
                maxHeight: dimensions.height,
                position: 'relative',
                backgroundColor: '#FFF',
                [`.${prevPageClassName}`]: {
                    position: 'absolute',
                    zIndex: 1,
                },
                [`.${controlsClassName}`]: {
                    display: 'none',
                },
                [`&:hover .${controlsClassName}`]: {
                    display: 'flex',
                },
            }}
        >
            <Document file={file.url} onLoadSuccess={onDocLoad}>
                {ratio ? (
                    <>
                        {controls ? (
                            <div
                                style={{
                                    justifyContent: 'center',
                                    alignItems: 'center',
                                    height: '100%',
                                    width: '100%',
                                    position: 'absolute',
                                    zIndex: annotationZIndex + 1,
                                    userSelect: 'none',
                                }}
                            >
                                {isLoading ? (
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'center',
                                            alignItems: 'center',
                                            height: '100%',
                                            width: '100%',
                                        }}
                                    >
                                        <CircularProgress />
                                    </div>
                                ) : (
                                    ''
                                )}

                                <div
                                    className={controlsClassName}
                                    style={{
                                        position: 'absolute',
                                        bottom: 5,
                                        justifyContent: 'center',
                                        alignItems: 'center',
                                        width: '100%',
                                    }}
                                >
                                    <Stack
                                        sx={theme => ({
                                            opacity: 0.9,
                                            bgcolor: 'background.paper',
                                            p: 1,
                                            boxShadow: theme.shadows[2],
                                            borderRadius:
                                                theme.shape.borderRadius,
                                        })}
                                        direction={'row'}
                                        alignItems={'center'}
                                        spacing={3}
                                    >
                                        <IconButton
                                            onClick={() =>
                                                setPageNumber(pageNumber - 1)
                                            }
                                            disabled={pageNumber === 1}
                                        >
                                            <KeyboardArrowLeftIcon />
                                        </IconButton>
                                        <div>
                                            {pageNumber} / {numPages}
                                        </div>
                                        <IconButton
                                            onClick={() =>
                                                setPageNumber(pageNumber + 1)
                                            }
                                            disabled={pageNumber === numPages}
                                        >
                                            <KeyboardArrowRightIcon />
                                        </IconButton>
                                    </Stack>
                                </div>
                            </div>
                        ) : (
                            ''
                        )}

                        {isLoading && renderedPageNumber ? (
                            <Page
                                {...pdfDimensions}
                                className={prevPageClassName}
                                key={renderedPageNumber}
                                pageNumber={renderedPageNumber}
                            />
                        ) : (
                            ''
                        )}

                        {pageAnnotations.length > 0 ? (
                            <AssetAnnotationsOverlay
                                annotations={pageAnnotations}
                            />
                        ) : null}


                        {onNewAnnotation ? (
                            <AnnotateTool
                                onNewAnnotation={onNewAnnotation}
                                page={pageNumber}
                            />
                        ) : null}

                        <Page
                            {...pdfDimensions}
                            key={pageNumber}
                            pageNumber={pageNumber}
                            onRenderSuccess={() => {
                                if (pageRef.current === pageNumber) {
                                    setRenderedPageNumber(pageNumber);
                                }
                            }}
                        />
                    </>
                ) : (
                    ''
                )}
            </Document>
        </Box>
    );
}

pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${
    pdfjs.version
}/build/pdf.worker.min.mjs`;
