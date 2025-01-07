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
import AnnotateTool from "../Annotations/AnnotateTool.tsx";
import {AssetAnnotation} from "../Annotations/annotationTypes.ts";

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

    useEffect(() => {
        if (annotations && annotations.length > 0) {
            const goTo = annotations[annotations.length - 1].page;
            goTo && setPageNumber(goTo);
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
                    backgroundColor: '#FFF',
                    top: 0,
                    left: 0,
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
                                    userSelect: 'none',
                                }}
                            >
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
                                            zIndex: annotationZIndex + 1,
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
                                            <KeyboardArrowLeftIcon/>
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
                                            <KeyboardArrowRightIcon/>
                                        </IconButton>
                                    </Stack>
                                </div>
                            </div>
                        ) : (
                            ''
                        )}

                        {pageAnnotations.length > 0 ? (
                            <AssetAnnotationsOverlay
                                annotations={pageAnnotations}
                            />
                        ) : null}

                        <AnnotateTool
                            onNewAnnotation={onNewAnnotation}
                            page={pageNumber}
                        >
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
                            />
                        </AnnotateTool>
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
