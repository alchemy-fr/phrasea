import React from 'react';
import {PlayerProps} from "./index";
import {Box} from "@mui/material";
import { Document, Page } from 'react-pdf/dist/esm/entry.webpack';

type Props = {} & PlayerProps;

export default function PDFPlayer({
                                      file,
                                      thumbSize,
                                      onLoad,
                                      noInteraction,
                                  }: Props) {
    return <Box sx={theme => ({
        width: thumbSize,
        height: thumbSize,
        position: 'relative',
        backgroundColor: '#FFF',
    })}
    >
        <Document
            file={file.url} onLoadSuccess={onLoad}>
            <Page pageNumber={1}/>
        </Document>
    </Box>
}
