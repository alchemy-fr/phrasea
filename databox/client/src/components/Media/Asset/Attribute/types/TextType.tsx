import React from 'react';
import {AttributeFormatterProps, AttributeTypeInstance, AttributeWidgetProps} from "./types";
import {Box, TextField, TextFieldProps} from "@mui/material";
import {replaceHighlight} from "../Attributes";
import BaseType from "./BaseType";
import CopyAttribute, {copyToClipBoardClass} from "../CopyAttribute";

export default class TextType extends BaseType implements AttributeTypeInstance {
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
            value={value ?? ''}
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
                ? <Box
                    component={'ul'}
                    sx={{
                        [`.${copyToClipBoardClass}`]: {
                            display: 'inline-block',
                            visibility: 'hidden',
                            ml: 2,
                        },
                        [`li:hover .${copyToClipBoardClass}`]: {
                            visibility: 'visible',
                        }
                    }}
                >{finalValue.map((v: any, i: number) => <li key={i}>
                    {replaceHighlight(v)}
                    <CopyAttribute
                        value={value[i]}
                    />
                </li>)}</Box> : replaceHighlight(finalValue)}
        </>
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value.toString();
    }

    supportsMultiple(): boolean {
        return true;
    }

    public getFieldProps(): TextFieldProps {
        return {
            type: 'text',
        };
    }
}
