import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import {FormLabel} from '@mui/material';
import {AttributeEntity} from '../../../../../types.ts';
import AttributeEntitySelect, {
    AttributeEntityOption,
} from '../../../../Form/AttributeEntitySelect.tsx';
import BaseType from './BaseType.tsx';
import {getBestTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper';

export default class AttributeEntityType
    extends BaseType
    implements AttributeTypeInstance<AttributeEntity>
{
    renderWidget({
        value,
        name,
        onChange,
        id,
        readOnly,
        disabled,
        options,
    }: AttributeWidgetProps<AttributeEntity>): React.ReactNode {
        return (
            <>
                <FormLabel>{name}</FormLabel>
                <AttributeEntitySelect
                    id={id}
                    multiple={false}
                    list={options.list}
                    name={name}
                    disabled={readOnly || disabled}
                    value={value?.id}
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

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return getTranslatedValue(value) as React.ReactNode;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return getTranslatedValue(value);
    }
}

function getTranslatedValue(
    value: AttributeEntity | undefined
): string | undefined {
    if (!value) {
        return undefined;
    }

    return getBestTranslatedValue(value.translations, value.value);
}
