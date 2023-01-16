import React from 'react';
import {TextFieldProps} from "@mui/material";
import TextType from "./TextType";

export default class TextareaType extends TextType {

    protected getFieldProps(): TextFieldProps {
        return {
            type: 'text',
            rows: 3,
            multiline: true,
        };
    }
}
