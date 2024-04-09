import React, {PropsWithChildren, ReactNode} from 'react';
import {Asset} from '../../../types';
import SectionDivider from '../SectionDivider';
import {AttributeFormatContext} from '../../Media/Asset/Attribute/Format/AttributeFormatContext';
import {IconButton} from '@mui/material';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {AttributeType} from '../../../api/attributes';
import {AttributeFormat} from '../../Media/Asset/Attribute/types/types';
import {getAttributeType} from '../../Media/Asset/Attribute/types';
import {groupValueTypes} from '../GroupValue/types';
import assetClasses from '../classes';

type Props = PropsWithChildren<{
    asset: Asset;
    toolbarHeight: number;
}>;

export default function GroupRow({
    asset: {groupValue},
    children,
    toolbarHeight,
}: Props) {
    const formatContext = React.useContext(AttributeFormatContext);

    if (!groupValue) {
        return children as ReactNode;
    }

    const {values, type, name} = groupValue;

    return (
        <>
            <SectionDivider
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
                top={toolbarHeight}
            >
                {formatContext.hasFormats(type) && (
                    <IconButton
                        className={assetClasses.toggleFormat}
                        onClick={() => formatContext.toggleFormat(type)}
                        sx={{
                            mr: 1,
                        }}
                    >
                        <VisibilityIcon fontSize={'small'} />
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
            {children}
        </>
    );
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
