import {useCallback, useState} from 'react';
import {pdfjs} from 'react-pdf';
import 'react-pdf/dist/esm/Page/TextLayer.css';
import 'react-pdf/dist/esm/Page/AnnotationLayer.css';
import {IconButton} from '@mui/material';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import PdfView from './PdfView.tsx';
import {FilePlayerClasses, FilePlayerProps} from '../types';
import {getRatioDimensions} from '@alchemy/core';
import classNames from 'classnames';

type Props = FilePlayerProps;

export default function PDFPlayer({file, controls, onLoad, dimensions}: Props) {
    const [ratio, setRatio] = useState<number>();
    const [numPages, setNumPages] = useState<number>();
    const [pageNumber, setPageNumber] = useState<number>(1);

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

    return (
        <>
            <div
                className={classNames({
                    [FilePlayerClasses.PlayerControls]: true, // TODO move to controls
                })}
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
                    zoomStep={{
                        current: 1,
                        maxReached: 1,
                    }}
                    onRenderSuccess={() => {}}
                />
            </div>
            {controls ? (
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
            ) : undefined}
        </>
    );
}

pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${
    pdfjs.version
}/build/pdf.worker.min.mjs`;
