import React from "react";
import {AttributeFormat} from "../types/types";

export type TAttributeFormatContext = {
    formats: Formats;
    changeFormat: (type: Type, newFormat: AttributeFormat) => void;
};

type Type = string;
export type {Type as AttributeFormatType};

type Formats = Record<Type, AttributeFormat>;
export type {Formats as AttributeFormats};

export const AttributeFormatContext = React.createContext<TAttributeFormatContext>({
    formats: {},
    changeFormat: () => {
    },
});
