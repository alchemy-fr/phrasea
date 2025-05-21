import {AttributeFormat} from '../types/types';
import React from 'react';
import {AttributeType} from "../../../../../api/attributes.ts";

export type TAttributeFormatContext = {
    getFormat(type: AttributeType, definitionId?: AttributeDefinitionId): AttributeFormat | undefined;
    changeFormat: (type: AttributeType, newFormat: AttributeFormat, definitionId?: AttributeDefinitionId) => void;
    toggleFormat: (type: AttributeType, definitionId?: AttributeDefinitionId) => void;
    hasFormats: (type: AttributeType) => boolean;
};

type AttributeDefinitionId = string;

type Formats = Record<AttributeDefinitionId | AttributeType, AttributeFormat>;
export type {Formats as AttributeFormats};

export const AttributeFormatContext =
    React.createContext<TAttributeFormatContext>({
        getFormat: () => undefined,
        changeFormat: () => {},
        toggleFormat: () => {},
        hasFormats: () => {
            return false;
        },
    });
