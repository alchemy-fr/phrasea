import React from "react";
import {isRtlLocale} from "../../../../lib/lang";
import {AttributeFormatContext} from "./Format/AttributeFormatContext";
import VisibilityIcon from '@mui/icons-material/Visibility';
import {IconButton} from "@mui/material";
import {getAttributeType} from "./types";
import PushPinIcon from '@mui/icons-material/PushPin';
import CopyAttribute from "./CopyAttribute";

type Props = {
    type: string;
    definitionId: string;
    locale: string | undefined;
    attributeName: string;
    value: any,
    highlight?: any,
    controls: boolean,
    multiple: boolean,
    togglePin: (definitionId: string) => void,
    pinnedAttributes: string[],
}

export default function AttributeRowUI({
    type,
    definitionId,
    locale,
    attributeName,
    value,
    highlight,
    multiple,
    togglePin,
    pinnedAttributes,
    controls,
}: Props) {
    const isRtl = isRtlLocale(locale);
    const formatContext = React.useContext(AttributeFormatContext);
    const formatter = getAttributeType(type);
    const availableFormats = formatter.getAvailableFormats();
    const pinned = pinnedAttributes.includes(definitionId);

    const toggleFormat = React.useCallback<React.MouseEventHandler<HTMLButtonElement>>((e) => {
        e.stopPropagation();
        const currentFormat = formatContext.formats[type];
        const currentIndex = currentFormat ? availableFormats.findIndex(f => f.name === currentFormat) ?? 0 : 0;

        formatContext.changeFormat(type, availableFormats[(currentIndex + 1) % availableFormats.length].name);
    }, [formatContext]);

    const valueFormatterProps = {
        value,
        highlight,
        locale,
        multiple,
        format: formatContext.formats[type],
    };

    return <div
        style={isRtl ? {
            direction: 'rtl'
        } : undefined}>
        <div className={'attr-name'}>
            {attributeName}
            {controls && availableFormats.length > 0 && <IconButton
                onClick={toggleFormat}
                sx={{
                    ml: 1,
                }}
            >
                <VisibilityIcon fontSize={'small'}/>
            </IconButton>}

            {controls && <CopyAttribute
                sx={{
                    ml: 1,
                }}
                value={formatter.formatValueAsString(valueFormatterProps)}
            />}

            {controls && <IconButton
                onClick={() => togglePin(definitionId)}
                sx={{
                    ml: 1,
                }}
            >
                <PushPinIcon
                    fontSize={'small'}
                    color={pinned ? 'success' : undefined}
                />
            </IconButton>}
        </div>
        <div
            className={'attr-val'}
            lang={locale}
        >
            {multiple && !formatter.supportsMultiple() ? <ul>
                {value ? value.map((v: any, i: number) => {
                    const formatProps = {
                        value: v,
                        highlight,
                        locale,
                        multiple,
                        format: formatContext.formats[type],
                    };

                    return <li
                        key={i}
                    >
                        {formatter.formatValue(formatProps)}
                        <CopyAttribute
                            value={formatter.formatValueAsString(formatProps)}
                        />
                    </li>;
                }) : ''}
            </ul> : <>
                {formatter.formatValue(valueFormatterProps)}
            </>}
        </div>
    </div>
}
