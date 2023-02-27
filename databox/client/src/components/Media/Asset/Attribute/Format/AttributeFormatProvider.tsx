import React, {PropsWithChildren} from 'react';
import {AttributeFormatContext, AttributeFormats, TAttributeFormatContext,} from "./AttributeFormatContext";
import {getAttributeType} from "../types";

type Props = PropsWithChildren<{}>;

export default function AttributeFormatProvider({children}: Props) {
    const [formats, setFormats] = React.useState<AttributeFormats>({});

    const value = React.useMemo<TAttributeFormatContext>(() => {
        const changeFormat: TAttributeFormatContext['changeFormat'] = (type, format) => {
            setFormats(p => ({
                ...p,
                [type]: format
            }));
        }

        const toggleFormat: TAttributeFormatContext['toggleFormat'] = (type) => {
            const formatter = getAttributeType(type);
            const availableFormats = formatter.getAvailableFormats();
            const currentFormat = formats[type];
            const currentIndex = currentFormat ? availableFormats.findIndex(f => f.name === currentFormat) ?? 0 : 0;
            changeFormat(type, availableFormats[(currentIndex + 1) % availableFormats.length].name);
        }

        const hasFormats: TAttributeFormatContext['hasFormats'] = (type) => {
            const formatter = getAttributeType(type);
            const availableFormats = formatter.getAvailableFormats();

            return availableFormats.length > 0;
        }

        return {
            changeFormat,
            toggleFormat,
            hasFormats,
            formats,
        };
    }, [formats, setFormats]);

    return <AttributeFormatContext.Provider value={value}>
        {children}
    </AttributeFormatContext.Provider>
}
