import React from "react";
import {isRtlLocale} from "../../../../lib/lang";
import {AttributeFormatContext} from "./Format/AttributeFormatContext";
import VisibilityIcon from '@mui/icons-material/Visibility';
import {IconButton} from "@mui/material";
import {getAttributeType} from "./types";

type Props = {
    type: string;
    locale: string | undefined;
    attributeName: string;
    value: any,
    highlight?: any,
    multiple: boolean,
}

export default function AttributeRowUI({
                                           type,
                                           locale,
                                           attributeName,
                                           value,
                                           highlight,
                                           multiple,
                                       }: Props) {
    const isRtl = isRtlLocale(locale);
    const formatContext = React.useContext(AttributeFormatContext);
    const formatter = getAttributeType(type);
    const availableFormats = formatter.getAvailableFormats();

    const toggleFormat = React.useCallback<React.MouseEventHandler<HTMLButtonElement>>((e) => {
        e.stopPropagation();
        const currentFormat = formatContext.formats[type];
        const currentIndex = currentFormat ? availableFormats.findIndex(f => f.name === currentFormat) ?? 0 : 0;

        formatContext.changeFormat(type, availableFormats[(currentIndex + 1) % availableFormats.length].name);
    }, [formatContext]);

    return <div
        style={isRtl ? {
            direction: 'rtl'
        } : undefined}>
        <div className={'attr-name'}>
            {attributeName}
            {availableFormats.length > 0 && <IconButton
                onClick={toggleFormat}
                sx={{
                    ml: 1,
                }}
            >
                <VisibilityIcon fontSize={'small'}/>
            </IconButton>}
        </div>
        <div
            className={'attr-val'}
            lang={locale}
        >
            {multiple && !formatter.supportsMultiple() ? <ul>
                {value ? value.map((v: any, i: number) => <li
                    key={i}
                >
                    {formatter.formatValue({
                        value: v,
                        highlight,
                        locale,
                        multiple,
                        format: formatContext.formats[type],
                    })}
                </li>) : ''}
            </ul> : formatter.formatValue({
                value,
                highlight,
                locale,
                multiple,
                format: formatContext.formats[type],
            })}
        </div>
    </div>
}
