import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import {TextField, TextFieldProps} from '@mui/material';
import {replaceHighlight} from '../AttributeHighlights.tsx';
import BaseType from './BaseType';
import React from 'react';

export default class TextType
    extends BaseType
    implements AttributeTypeInstance<string>
{
    renderWidget({
        value,
        onChange,
        readOnly,
        id,
        disabled,
        required,
        name,
        autoFocus,
        isRtl,
        indeterminate,
        inputRef,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <TextField
                {...this.getFieldProps()}
                id={id}
                inputProps={{
                    readOnly,
                    style: readOnly
                        ? {
                              cursor: 'not-allowed',
                          }
                        : undefined,
                }}
                inputRef={inputRef}
                fullWidth
                disabled={readOnly || disabled}
                label={name}
                onChange={e => onChange(e.target.value)}
                value={value ?? ''}
                required={required}
                autoFocus={autoFocus}
                style={{
                    direction: isRtl ? 'rtl' : undefined,
                    cursor: 'not-allowed',
                }}
                placeholder={indeterminate ? '[multiple values]' : undefined}
            />
        );
    }

    formatValue({value, highlight}: AttributeFormatterProps): React.ReactNode {
        return replaceHighlight(highlight || value);
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.toString();
    }

    public getFieldProps(): TextFieldProps {
        return {
            type: 'text',
        };
    }
}
