import React, {useCallback, useState} from 'react';
import {PlayerProps} from "./index";
import {Document, Page} from 'react-pdf/dist/esm/entry.webpack';
import {getMaxVideoDimensions} from "./VideoPlayer";
import {PDFPageProxy} from "react-pdf";

type Props = {} & PlayerProps;

export default function PDFPlayer({
                                      file,
                                      minDimensions,
                                      maxDimensions,
                                      onLoad,
                                      noInteraction,
                                  }: Props) {
    const [ratio, setRatio] = useState<number>();
    const pdfDimensions = getMaxVideoDimensions(maxDimensions, ratio);
    const onDocLoad = useCallback((pdf: any) => {
        if (onLoad) {
            pdf.getPage(1).then((page: PDFPageProxy) => {
                setRatio(page.view[3] / page.view[2]);
            });
        }
    }, [onLoad]);

    return <div style={{
        maxWidth: maxDimensions.width,
        maxHeight: maxDimensions.height,
        minWidth: minDimensions?.width,
        minHeight: minDimensions?.height,
        position: 'relative',
        backgroundColor: '#FFF',
    }}
    >
        <Document
            file={file.url} onLoadSuccess={onDocLoad}>
            {ratio && <Page
                {...pdfDimensions}
                pageNumber={1}
                onLoadSuccess={onLoad}
            />}
        </Document>
    </div>
}
