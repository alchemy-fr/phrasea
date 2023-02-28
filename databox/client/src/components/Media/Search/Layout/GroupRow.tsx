import React, {PropsWithChildren, ReactNode} from 'react';
import {Asset} from "../../../../types";
import SectionDivider from "./SectionDivider";
import {AttributeFormatContext} from "../../Asset/Attribute/Format/AttributeFormatContext";
import {IconButton, styled} from "@mui/material";
import VisibilityIcon from "@mui/icons-material/Visibility";
import {AttributeType} from "../../../../api/attributes";
import {AttributeFormat} from "../../Asset/Attribute/types/types";
import {getAttributeType} from "../../Asset/Attribute/types";

type Props = PropsWithChildren<{
    asset: Asset;
    searchMenuHeight: number;
}>;

export default function GroupRow({
    asset: {groupValue},
    children,
    searchMenuHeight,
}: Props) {
    const formatContext = React.useContext(AttributeFormatContext);

    if (!groupValue) {
        return <>{children}</>
    }

    const {
        values,
        type,
    } = groupValue;

    const toggleFormatClass = 'toggle-format';

    return <>
        <SectionDivider
            dividerSx={{
                [`.${toggleFormatClass}`]: {
                    display: 'none',
                    position: 'absolute',
                    left: 0,
                    top: 0,
                    ml: 1,
                },
                ':hover': {
                    [`.${toggleFormatClass}`]: {
                        display: 'flex',
                    },
                },
                'span + span': {
                    ml: 1,
                },
                '.MuiChip-root': {
                    my: -1,
                }
            }}
            top={searchMenuHeight}
        >
            {formatContext.hasFormats(type) && <IconButton
                className={toggleFormatClass}
                onClick={() => formatContext.toggleFormat(type)}
                sx={{
                    mr: 1,
                }}
            >
                <VisibilityIcon
                    fontSize={'small'}
                />
            </IconButton>}
            {values.length > 0 ? values.map((v, i) => <span key={i}>
                {formatAttribute(type, v, formatContext.formats[type])}
            </span>) : 'None'}
        </SectionDivider>
        {children}
    </>
}

export function formatAttribute(type: AttributeType, value: any, format?: AttributeFormat): ReactNode | undefined {
    if (!value) {
        return;
    }

    const formatter = getAttributeType(type);

    return formatter.formatValue({
        value,
        locale: undefined,
        multiple: false,
        highlight: undefined,
        format,
    });
}
