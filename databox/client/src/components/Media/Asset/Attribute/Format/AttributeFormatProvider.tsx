import React, {PropsWithChildren} from 'react';
import {
    AttributeFormatContext,
    AttributeFormats,
    TAttributeFormatContext,
} from './AttributeFormatContext';
import {getAttributeType} from '../types';
import {AttributeType} from '../../../../../api/attributes.ts';

type Props = PropsWithChildren<{}>;

export default function AttributeFormatProvider({children}: Props) {
    const [formats, setFormats] = React.useState<AttributeFormats>(
        {} as AttributeFormats
    );

    const value = React.useMemo<TAttributeFormatContext>(() => {
        const getFormat: TAttributeFormatContext['getFormat'] = (
            type: AttributeType,
            definitionId?: string
        ) => {
            if (definitionId) {
                return formats[definitionId] ?? formats[type];
            }

            return formats[type];
        };

        const changeFormat: TAttributeFormatContext['changeFormat'] = (
            type,
            format,
            definitionId
        ) => {
            setFormats(p => ({
                ...p,
                [type]: format,
                ...(definitionId ? {[definitionId]: format} : {}),
            }));
        };

        const toggleFormat: TAttributeFormatContext['toggleFormat'] = (
            type: AttributeType,
            definitionId
        ) => {
            const formatter = getAttributeType(type);
            const availableFormats = formatter.getAvailableFormats();
            const currentFormat = getFormat(type, definitionId);
            const currentIndex = currentFormat
                ? (availableFormats.findIndex(f => f.name === currentFormat) ??
                  0)
                : 0;
            changeFormat(
                type,
                availableFormats[(currentIndex + 1) % availableFormats.length]
                    .name,
                definitionId
            );
        };

        const hasFormats: TAttributeFormatContext['hasFormats'] = (
            type: AttributeType
        ) => {
            const formatter = getAttributeType(type);
            const availableFormats = formatter.getAvailableFormats();

            return availableFormats.length > 0;
        };

        return {
            changeFormat,
            toggleFormat,
            hasFormats,
            getFormat,
        };
    }, [formats, setFormats]);

    return (
        <AttributeFormatContext.Provider value={value}>
            {children}
        </AttributeFormatContext.Provider>
    );
}
