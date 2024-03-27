import {TAttributeFormatContext} from './Format/AttributeFormatContext';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {IconButton} from '@mui/material';
import {getAttributeType} from './types';
import PushPinIcon from '@mui/icons-material/PushPin';
import CopyAttribute from './CopyAttribute';
import React from 'react';
import {attributesClasses} from "./Attributes.tsx";
import {isRtlLocale} from "../../../../lib/lang.ts";

type Props = {
    type: string;
    definitionId: string;
    locale: string | undefined;
    attributeName: string;
    value: any;
    highlight?: any;
    displayControls: boolean;
    multiple: boolean;
    togglePin: (definitionId: string) => void;
    pinned: boolean;
    formatContext: TAttributeFormatContext;
};

export default function AttributeRowUI({
    type,
    definitionId,
    locale,
    attributeName,
    value,
    highlight,
    multiple,
    togglePin,
    pinned,
    displayControls,
    formatContext,
}: Props) {
    const isRtl = isRtlLocale(locale);
    const formatter = getAttributeType(type);
    const [overControls, setOverControls] = React.useState(false);

    const toggleFormat = React.useCallback<
        React.MouseEventHandler<HTMLButtonElement>
    >(
        e => {
            e.stopPropagation();
            formatContext.toggleFormat(type);
        },
        [formatContext]
    );

    const valueFormatterProps = {
        value,
        highlight,
        locale,
        multiple,
        format: formatContext.formats[type],
    };

    return (
        <div
            style={
                isRtl
                    ? {
                        direction: 'rtl',
                    }
                    : undefined
            }
            onMouseEnter={() => setOverControls(true)}
            onMouseLeave={() => setOverControls(false)}
        >
            <div className={attributesClasses.name}>
                {attributeName}
                {displayControls ? <div className={attributesClasses.controls}>
                    {overControls ? <>
                        {formatContext.hasFormats(type) && (
                            <IconButton
                                onClick={toggleFormat}
                            >
                                <VisibilityIcon/>
                            </IconButton>
                        )}

                        <CopyAttribute
                            value={formatter.formatValueAsString(
                                valueFormatterProps
                            )}
                        />

                        <IconButton
                            onClick={() => togglePin(definitionId)}
                        >
                            <PushPinIcon
                                color={pinned ? 'success' : undefined}
                            />
                        </IconButton>
                    </> : ''}
                </div> : ''}
            </div>
            <div className={attributesClasses.val} lang={locale}>
                {multiple && !formatter.supportsMultiple() ? (
                    <ul className={attributesClasses.list}>
                        {value
                            ? value.map((v: any, i: number) => {
                                const formatProps = {
                                    value: v,
                                    highlight,
                                    locale,
                                    multiple,
                                    format: formatContext.formats[type],
                                };

                                return (
                                    <li key={i}>
                                        {formatter.formatValue(formatProps)}
                                        {displayControls && overControls ? <CopyAttribute
                                            value={formatter.formatValueAsString(
                                                formatProps
                                            )}
                                        /> : ''}
                                    </li>
                                );
                            })
                            : null}
                    </ul>
                ) : (
                    <>{formatter.formatValue(valueFormatterProps)}</>
                )}
            </div>
        </div>
    );
}
