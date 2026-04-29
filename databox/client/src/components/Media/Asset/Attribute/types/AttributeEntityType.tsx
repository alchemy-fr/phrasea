import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import {Box, InputLabel} from '@mui/material';
import {AttributeEntity, AttributeEntityStatus} from '../../../../../types.ts';
import AttributeEntitySelect, {
    AttributeEntityOption,
} from '../../../../Form/AttributeEntitySelect.tsx';
import BaseType from './BaseType.tsx';

type EntityValue = {
    id: string;
    value: string | null;
    status: AttributeEntityStatus;
    createdAt: string;
};

export default class AttributeEntityType
    extends BaseType
    implements AttributeTypeInstance<AttributeEntity>
{
    renderWidget({
        labelAlreadyRendered,
        value,
        label,
        onChange,
        id,
        readOnly,
        disabled,
        options,
    }: AttributeWidgetProps<AttributeEntity>): React.ReactNode {
        return (
            <>
                {!labelAlreadyRendered && <InputLabel>{label}</InputLabel>}
                <AttributeEntitySelect
                    id={id}
                    multiple={false}
                    list={options.list}
                    disabled={readOnly || disabled}
                    value={value?.id}
                    workspaceId={options.workspaceId}
                    onChange={newValue => {
                        onChange(
                            (
                                (newValue || undefined) as
                                    | AttributeEntityOption
                                    | undefined
                            )?.item
                        );
                    }}
                />
            </>
        );
    }

    normalize(value: AttributeEntity | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value, t}: AttributeFormatterProps): React.ReactNode {
        if (
            value &&
            (value as EntityValue).status !== AttributeEntityStatus.Approved
        ) {
            if (value.status === AttributeEntityStatus.Pending) {
                return (
                    <Box
                        component={'span'}
                        sx={{
                            color: 'warning.main',
                            fontStyle: 'italic',
                        }}
                    >
                        {t(
                            'attribute.entity.pending',
                            '[Value Pending for approval]'
                        )}
                    </Box>
                );
            } else {
                return;
            }
        }

        return value?.value;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.value;
    }
}
