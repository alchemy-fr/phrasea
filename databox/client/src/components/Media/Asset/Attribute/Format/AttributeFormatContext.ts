import {AttributeFormat} from '../types/types';
import React from 'react';
import {AttributeType} from "../../../../../api/attributes.ts";

export type TAttributeFormatContext = {
    formats: Formats;
    changeFormat: (type: AttributeType, newFormat: AttributeFormat) => void;
    toggleFormat: (type: AttributeType) => void;
    hasFormats: (type: AttributeType) => boolean;
};


type Formats = Record<AttributeType, AttributeFormat>;
export type {Formats as AttributeFormats};

export const AttributeFormatContext =
    React.createContext<TAttributeFormatContext>({
        formats: {} as Formats,
        changeFormat: () => {},
        toggleFormat: () => {},
        hasFormats: () => {
            return false;
        },
    });
