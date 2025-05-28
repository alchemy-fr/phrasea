import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import {FormLabel} from '@mui/material';
import React from 'react';
import TagSelect, {TagOptions} from '../../../../Form/TagSelect.tsx';
import BaseType from './BaseType.tsx';
import TagNode from '../../../../Ui/TagNode.tsx';
import {Tag} from '../../../../../types.ts';

export default class TagsType
    extends BaseType
    implements AttributeTypeInstance<Tag>
{
    renderWidget({
        value,
        name,
        onChange,
        id,
        readOnly,
        disabled,
    }: AttributeWidgetProps<Tag>): React.ReactNode {
        return (
            <>
                <FormLabel>{name}</FormLabel>
                <TagSelect
                    id={id}
                    multiple={false}
                    name={name}
                    disabled={readOnly || disabled}
                    value={value?.['@id']}
                    onChange={newValue => {
                        onChange(
                            ((newValue || undefined) as TagOptions | undefined)
                                ?.item
                        );
                    }}
                />
            </>
        );
    }

    normalize(value: Tag | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return (
            <TagNode
                name={value.nameTranslated}
                color={value.color}
                size={'small'}
            />
        );
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.nameTranslated;
    }
}
