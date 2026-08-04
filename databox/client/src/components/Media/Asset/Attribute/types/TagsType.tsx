import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import {FormLabel} from '@mui/material';
import React from 'react';
import TagSelect from '../../../../Form/TagSelect.tsx';
import BaseType from './BaseType.tsx';
import TagNode from '../../../../Ui/TagNode.tsx';
import {Tag} from '../../../../../types.ts';
import {EntityName} from '../../../../../api/types.ts';
import {SelectOption} from '@alchemy/react-form';

export default class TagsType
    extends BaseType
    implements AttributeTypeInstance<string>
{
    public entityIri = EntityName.Tag;
    public isRich = true;

    renderWidget({
        labelAlreadyRendered,
        value,
        label,
        onChange,
        id,
        readOnly,
        disabled,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <>
                {!labelAlreadyRendered && <FormLabel>{label}</FormLabel>}
                <TagSelect
                    id={id}
                    multiple={false}
                    useIRI={false}
                    disabled={readOnly || disabled}
                    value={value}
                    onChange={newValue => {
                        onChange(
                            newValue && typeof newValue === 'object'
                                ? (newValue as SelectOption).value
                                : (newValue as unknown as string | undefined)
                        );
                    }}
                />
            </>
        );
    }

    normalize(value: Tag | undefined): string | undefined {
        if (value && typeof value === 'string') {
            return value;
        }

        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return (
            <TagNode
                name={value.displayName}
                color={value.color}
                size={'small'}
            />
        );
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.displayName;
    }
}
