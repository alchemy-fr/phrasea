import React from 'react';
import {AttributeFormatterProps, AttributeWidgetProps} from "./types";
import TextareaType from "./TextareaType";
import CodeEditor from "../../Widgets/CodeEditor";

export default class CodeType extends TextareaType {
    renderWidget({
        value,
        onChange,
        id,
        readOnly,
    }: AttributeWidgetProps): React.ReactNode {
        return <CodeEditor
            readOnly={readOnly}
            mode={this.getAceMode()}
            highlightActiveLine={true}
            onChange={onChange}
            name={`code-editor-${id}`}
            value={value}
            prettify={this.prettifyCode}
        />
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <>{value ? <CodeEditor
            readOnly={true}
            mode={this.getAceMode()}
            value={this.prettifyCode(value)}
            style={{
                maxHeight: 200,
                overflow: "auto",
                whiteSpace: "pre-wrap"
            }}
        /> : value}</>
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
