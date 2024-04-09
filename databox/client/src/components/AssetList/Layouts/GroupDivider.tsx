import React, {PropsWithChildren, ReactNode} from 'react';
import {GroupValue} from '../../../types';
import SectionDivider from '../SectionDivider';
import {AttributeFormatContext} from '../../Media/Asset/Attribute/Format/AttributeFormatContext';
import {IconButton} from '@mui/material';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {groupValueTypes} from '../GroupValue/types';
import assetClasses from '../classes';
import {AttributeType} from "../../../api/attributes.ts";
import {AttributeFormat} from "../../Media/Asset/Attribute/types/types";
import {getAttributeType} from "../../Media/Asset/Attribute/types";

type Props = PropsWithChildren<{
    groupValue: GroupValue;
    top?: number | undefined;
}>;

export default function GroupDivider({
    groupValue,
    top,
}: Props) {
    const formatContext = React.useContext(AttributeFormatContext);

    const {values, type, name} = groupValue;

    return <SectionDivider
        dividerSx={{
            [`.${assetClasses.toggleFormat}`]: {
                display: 'none',
                position: 'absolute',
                left: 0,
                top: 0,
                ml: 1,
            },
            ':hover': {
                [`.${assetClasses.toggleFormat}`]: {
                    display: 'flex',
                },
            },
            'span + span': {
                ml: 1,
            },
            '.MuiChip-root': {
                my: -1,
            },
        }}
        top={top}
    >
        {formatContext.hasFormats(type) && (
            <IconButton
                className={assetClasses.toggleFormat}
                onClick={() => formatContext.toggleFormat(type)}
                sx={{
                    mr: 1,
                }}
            >
                <VisibilityIcon fontSize={'small'}/>
            </IconButton>
        )}
        {values.length > 0
            ? values.map((v, i) => (
                <span key={i}>
                              {groupValueTypes[name]
                                  ? groupValueTypes[name](v)
                                  : formatAttribute(
                                      type,
                                      v,
                                      formatContext.formats[type]
                                  )}
                          </span>
            ))
            : 'None'}
    </SectionDivider>
}

export function formatAttribute(
    type: AttributeType,
    value: any,
    format?: AttributeFormat
): ReactNode | undefined {
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
