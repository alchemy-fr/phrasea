import {AttributeFormatterProps, AttributeWidgetProps} from './types';
import TextareaType from './TextareaType';
import CodeEditor from '../../Widgets/CodeEditor';
import {FormLabel} from '@mui/material';
import React from 'react';

export default class CodeType extends TextareaType {
    renderWidget({
        value,
        name,
        onChange,
        id,
        readOnly,
        disabled,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <>
                <FormLabel>{name}</FormLabel>
                <CodeEditor
                    readOnly={readOnly || disabled || false}
                    mode={this.getAceMode()}
                    highlightActiveLine={true}
                    onChange={onChange}
                    name={`code-editor-${id}`}
                    value={value}
                    prettify={this.prettifyCode}
                />
            </>
        );
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <>{value ? this.prettifyCode(value) : value}</>;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value ? this.prettifyCode(value) : value;
    }

    protected getAceMode(): string {
        return 'text';
    }

    protected prettifyCode(code: string): string {
        return code;
    }
}
