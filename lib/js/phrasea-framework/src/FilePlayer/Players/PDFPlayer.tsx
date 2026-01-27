import {useCallback, useEffect, useState} from 'react';
import {pdfjs} from 'react-pdf';
import 'react-pdf/dist/esm/Page/TextLayer.css';
import 'react-pdf/dist/esm/Page/AnnotationLayer.css';
import {IconButton, Paper} from '@mui/material';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import PdfView from './PdfView.tsx';
import {FilePlayerClasses, FilePlayerProps, ZoomStepState} from '../types';
import {createStrictDimensions, getRatioDimensions} from '@alchemy/core';
import ZoomOutIcon from '@mui/icons-material/ZoomOut';
import ZoomInIcon from '@mui/icons-material/ZoomIn';

type Props = FilePlayerProps;

export default function PDFPlayer({
    file,
    controls,
    onLoad,
    dimensions: forcedDimensions,
}: Props) {
    const [ratio, setRatio] = useState<number>();
    const [numPages, setNumPages] = useState<number>();
    const [pageNumber, setPageNumber] = useState<number>(1);
    const dimensions = createStrictDimensions(forcedDimensions ?? {width: 200});
    const [zoomStep, setZoomStep] = useState<ZoomStepState>({
        current: 1,
        maxReached: 1,
    });

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

    const increaseZoomStep = useCallback(
        (inc: number): void => {
            setZoomStep(p => {
                const zoomSteps = [
                    0.1, 0.25, 0.5, 0.75, 1, 1.5, 2, 3, 4, 5, 6, 7, 8, 9, 10,
                ];

                let step = 0;
                for (let i = 0; i < zoomSteps.length; i++) {
                    if (p.current >= zoomSteps[i]) {
                        step = i;
                    }
                }

                step += inc;
                if (step < 0) {
                    step = 0;
                } else if (step >= zoomSteps.length) {
                    step = zoomSteps.length - 1;
                }

                const current = zoomSteps[step];

                return {
                    current,
                    maxReached: Math.max(p.maxReached, current),
                };
            });
        },
        [setZoomStep]
    );

    useEffect(() => {
        setZoomStep(p => ({
            ...p,
            maxReached: p.current,
        }));
    }, [file.url, pageNumber]);

    return (
        <>
            <div
                style={{
                    position: 'relative',
                    backgroundColor: '#FFF',
                }}
            >
                <PdfView
                    file={file.url}
                    onLoadSuccess={onDocLoad}
                    ratio={ratio}
                    pdfDimensions={pdfDimensions}
                    pageNumber={pageNumber}
                    zoomStep={zoomStep}
                    onRenderSuccess={() => {}}
                />
                {controls ? (
                    <Paper
                        sx={{
                            zIndex: 1000,
                            position: 'absolute',
                            bottom: 8,
                            left: '50%',
                            transform: 'translateX(-50%)',
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                            gap: 1,
                            padding: 1,
                            userSelect: 'none',
                        }}
                        className={FilePlayerClasses.PlayerControls}
                    >
                        <div>
                            <IconButton
                                onClick={() => increaseZoomStep(-1)}
                                disabled={zoomStep.current <= 0.1}
                            >
                                <ZoomOutIcon />
                            </IconButton>
                        </div>
                        <div
                            style={{
                                minWidth: 40,
                                textAlign: 'center',
                            }}
                        >
                            {Math.round(zoomStep.current * 100)}%
                        </div>
                        <div>
                            <IconButton
                                onClick={() => increaseZoomStep(1)}
                                disabled={zoomStep.current >= 10}
                            >
                                <ZoomInIcon />
                            </IconButton>
                        </div>
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
                    </Paper>
                ) : undefined}
            </div>
        </>
    );
}

pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${
    pdfjs.version
}/build/pdf.worker.min.mjs`;
