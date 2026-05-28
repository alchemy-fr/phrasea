import {AttributeEntity} from '../../../../types.ts';
import TagColor from '../Facets/TagColor.tsx';
import React from 'react';
import {
    formatAttributeEntityLabel,
    FormatAttributeEntityLabelOptions,
} from '../../../../api/attributeEntity.ts';
import {ListItemText} from '@mui/material';
import {FlexRow} from '@alchemy/phrasea-ui';

type Props = {
    data: AttributeEntity | undefined; // Entity may be out of sync, so undefined is possible
    inList?: boolean;
    suffix?: React.ReactNode;
    options?: FormatAttributeEntityLabelOptions;
};

export default function AttributeEntityListText({
    data,
    options,
    suffix,
    inList,
}: Props) {
    const text = data ? formatAttributeEntityLabel(data, options) : '';

    const label = (
        <>
            {text}
            {suffix}
        </>
    );

    const labelNode = inList ? <ListItemText primary={label} /> : label;

    const node = data?.color ? (
        <>
            <TagColor color={data.color} />
            {labelNode}
        </>
    ) : (
        labelNode
    );

    if (!inList) {
        return (
            <FlexRow
                style={{
                    display: 'inline-flex',
                }}
            >
                {node}
            </FlexRow>
        );
    }

    return node;
}
