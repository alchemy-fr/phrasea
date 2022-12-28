import React from 'react';
import {TextFieldProps} from "@mui/material";
import CodeType from "./CodeType";
import "ace-builds/src-noconflict/mode-json";
import {AttributeFormatterProps} from "./types";


export default class HtmlType extends CodeType {
    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <div dangerouslySetInnerHTML={{__html: value}}/>
    }

    protected getAceMode(): string {
        return 'html';
    }

    protected getFieldProps(): TextFieldProps {
        return {
            type: 'html',
        };
    }
}
