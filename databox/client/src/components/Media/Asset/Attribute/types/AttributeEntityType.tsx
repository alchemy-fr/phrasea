import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import {Box, InputLabel} from '@mui/material';
import {AttributeEntity, AttributeEntityStatus} from '../../../../../types.ts';
import AttributeEntitySelect from '../../../../Form/AttributeEntitySelect.tsx';
import BaseType from './BaseType.tsx';
import AttributeEntityListText from '../AttributeEntityListText.tsx';
import {getBestTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import {EntityName} from '../../../../../api/types.ts';

type EntityValue = {
    id: string;
    value: string | null;
    emoji?: string;
    color?: string;
    status: AttributeEntityStatus;
    createdAt: string;
};

export default class AttributeEntityType
    extends BaseType
    implements AttributeTypeInstance<string>
{
    public entityIri = EntityName.Entity;

    renderWidget({
        labelAlreadyRendered,
        value,
        label,
        onChange,
        id,
        readOnly,
        disabled,
        options,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <>
                {!labelAlreadyRendered && <InputLabel>{label}</InputLabel>}
                <AttributeEntitySelect
                    id={id}
                    multiple={false}
                    list={options.list}
                    disabled={readOnly || disabled}
                    value={value}
                    workspaceId={options.workspaceId}
                    onChange={newValue => {
                        onChange(
                            newValue && typeof newValue === 'object'
                                ? newValue.value
                                : (newValue as unknown as string | undefined)
                        );
                    }}
                />
            </>
        );
    }

    normalize(value: AttributeEntity | undefined): string | undefined {
        if (value && typeof value === 'string') {
            return value;
        }

        return value?.id;
    }

    formatValue(props: AttributeFormatterProps): React.ReactNode {
        const {value, t} = props;

        if (!value) {
            return null;
        }

        const status = (value as EntityValue | undefined)?.status;

        if (undefined !== status && status !== AttributeEntityStatus.Approved) {
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
                return null;
            }
        }

        return <AttributeEntityListText data={value as AttributeEntity} />;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        if (!value) {
            return;
        }

        return getBestTranslatedValue(value.translations, value.value);
    }
}
