import React, {useCallback, useState} from 'react';
import {PlayerProps} from "./index";
import {Box} from "@mui/material";
import {Document, Page} from 'react-pdf/dist/esm/entry.webpack';
import {PDFPageProxy} from "react-pdf";

type Props = {} & PlayerProps;

export default function PDFPlayer({
                                      file,
                                      thumbSize,
                                      onLoad,
                                      noInteraction,
                                  }: Props) {
    let size: number;
    if (typeof thumbSize === 'string') {
        const docHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
        size = docHeight * (parseInt(thumbSize.substring(0, thumbSize.length - 2)) / 100);
    } else {
        size = thumbSize;
    }

    const onDocLoad = useCallback((pdf: any) => {
        if (onLoad) {
            pdf.getPage(1).then(onLoad);
        }
    }, [onLoad]);

    return <div style={{
        maxWidth: thumbSize,
        maxHeight: thumbSize,
        position: 'relative',
        backgroundColor: '#FFF',
    }}
    >
        <Document
            file={file.url} onLoadSuccess={onDocLoad}>
            <Page
                height={size}
                pageNumber={1}
            />
        </Document>
    </div>
}
