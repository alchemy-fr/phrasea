import React from 'react';
import {AttributeFormatterProps, AttributeType, AttributeWidgetProps} from "./types";
import {TextField, TextFieldProps} from "@mui/material";
import {replaceHighlight} from "../Attributes";
import BaseType from "./BaseType";

export default class TextType extends BaseType implements AttributeType {
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
                 }: AttributeWidgetProps): React.ReactNode {
        return <TextField
            {...this.getFieldProps()}
            id={id}
            inputProps={{
                readOnly,
                style: readOnly ? {
                    cursor: 'not-allowed',
                } : undefined,
            }}
            fullWidth
            disabled={readOnly || disabled}
            label={name}
            onChange={(e) => onChange(e.target.value)}
            value={value}
            required={required}
            autoFocus={autoFocus}
            style={{
                direction: isRtl ? 'rtl' : undefined,
                cursor: 'not-allowed',
            }}
            placeholder={indeterminate ? '[multiple values]' : undefined}
        />
    }

    formatValue({value, locale, highlight, multiple}: AttributeFormatterProps): React.ReactNode {
        const finalValue = highlight || value;

        return <>
            {finalValue && multiple
                ? <ul>{finalValue.map((v: any, i: number) => <li key={i}>
                    {replaceHighlight(v)}
                </li>)}</ul> : replaceHighlight(finalValue)}
        </>
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value.toString();
    }

    protected getFieldProps(): TextFieldProps {
        return {
            type: 'text',
        };
    }

    supportsMultiple(): boolean {
        return true;
    }
}
