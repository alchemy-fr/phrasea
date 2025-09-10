import {AttributeFormat} from '../types/types';
import React from 'react';

import {AttributeType} from '../../../../../api/types.ts';

export type TAttributeFormatContext = {
    getFormat(
        type: AttributeType,
        definitionId?: AttributeDefinitionId
    ): AttributeFormat | undefined;
    getFormatTitle(
        type: AttributeType,
        definitionId?: AttributeDefinitionId
    ): string | undefined;
    changeFormat: (
        type: AttributeType,
        newFormat: AttributeFormat,
        definitionId?: AttributeDefinitionId
    ) => void;
    toggleFormat: (
        type: AttributeType,
        definitionId?: AttributeDefinitionId
    ) => void;
    hasFormats: (type: AttributeType) => boolean;
};

type AttributeDefinitionId = string;

type Formats = Record<AttributeDefinitionId | AttributeType, AttributeFormat>;
export type {Formats as AttributeFormats};

export const AttributeFormatContext =
    React.createContext<TAttributeFormatContext>({
        getFormat: () => undefined,
        getFormatTitle: () => undefined,
        changeFormat: () => {},
        toggleFormat: () => {},
        hasFormats: () => {
            return false;
        },
    });
