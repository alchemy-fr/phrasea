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
import classNames from 'classnames';

type Props = FilePlayerProps;

export default function PDFPlayer({file, controls, onLoad, dimensions: forcedDimensions}: Props) {
    const [ratio, setRatio] = useState<number>();
    const [numPages, setNumPages] = useState<number>();
    const [pageNumber, setPageNumber] = useState<number>(1);
    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: 200});
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
                const current =
                    p.current < 1 || p.current + inc > 1
                        ? p.current + inc
                        : p.current + inc / 10;
                if (current === p.current) {
                    return p;
                }

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

    console.log('zoomStep', zoomStep);

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
                        }}
                        className={FilePlayerClasses.PlayerControls}
                    >
                        <div>
                            <IconButton
                                onClick={() => increaseZoomStep(-1)}
                                disabled={zoomStep.current <= 0.1}
                            >
                                <span style={{fontSize: '18px'}}>-</span>
                            </IconButton>
                        </div>
                        <div>
                            <span>{Math.round(zoomStep.current * 100)}%</span>
                        </div>
                        <div>
                            <IconButton
                                onClick={() => increaseZoomStep(1)}
                                disabled={zoomStep.current >= 10}
                            >
                                <span style={{fontSize: '18px'}}>+</span>
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
