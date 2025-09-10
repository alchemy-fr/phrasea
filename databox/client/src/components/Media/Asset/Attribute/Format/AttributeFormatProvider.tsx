import React, {PropsWithChildren} from 'react';
import {
    AttributeFormatContext,
    AttributeFormats,
    TAttributeFormatContext,
} from './AttributeFormatContext';
import {getAttributeType} from '../types';

import {AttributeType} from '../../../../../api/types.ts';
import {useTranslation} from 'react-i18next';

type Props = PropsWithChildren<{}>;

export default function AttributeFormatProvider({children}: Props) {
    const {i18n} = useTranslation();
    const [formats, setFormats] = React.useState<AttributeFormats>(
        {} as AttributeFormats
    );

    const formatterOptions = {
        uiLocale: i18n.language,
    };

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

        const getFormatTitle: TAttributeFormatContext['getFormatTitle'] = (
            type: AttributeType,
            definitionId?: string
        ) => {
            const formatter = getAttributeType(type);

            return formatter
                .getAvailableFormats(formatterOptions)
                .find(f => f.name === getFormat(type, definitionId))?.title;
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
            const availableFormats =
                formatter.getAvailableFormats(formatterOptions);
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
            const availableFormats =
                formatter.getAvailableFormats(formatterOptions);

            return availableFormats.length > 0;
        };

        return {
            changeFormat,
            toggleFormat,
            hasFormats,
            getFormat,
            getFormatTitle,
        };
    }, [formats, setFormats]);

    return (
        <AttributeFormatContext.Provider value={value}>
            {children}
        </AttributeFormatContext.Provider>
    );
}
