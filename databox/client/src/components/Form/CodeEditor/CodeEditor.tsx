import AceEditor, {IAceEditorProps} from 'react-ace';
import React, {MutableRefObject} from 'react';
import 'ace-builds/src-noconflict/theme-monokai';
import 'ace-builds/src-noconflict/ext-language_tools';
import 'ace-builds/src-noconflict/mode-yaml';
import 'ace-builds/src-noconflict/mode-html';
import 'ace-builds/src-noconflict/mode-json';

export type CodeEditorProps = {
    prettify?: (code: string) => string;
    editorRef?: MutableRefObject<AceEditor | null>;
} & IAceEditorProps;

export default function CodeEditor({
    value: initialValue,
    onChange,
    prettify,
    editorRef,
    mode,
    ...rest
}: CodeEditorProps) {
    const [value, setValue] = React.useState<string | undefined>();

    React.useEffect(() => {
        try {
            if (initialValue) {
                setValue(prettify ? prettify(initialValue) : initialValue);
            }
        } catch (_e) {
            setValue(initialValue);
        }
    }, [initialValue]);

    const changeHandler = React.useCallback(
        (value: string) => {
            onChange?.(value);
            setValue(value);
        },
        [onChange]
    );

    return (
        <div
            onClick={e => e.stopPropagation()}
            onMouseDown={e => e.stopPropagation()}
        >
            <AceEditor
                ref={editorRef}
                theme="monokai"
                fontSize={15}
                mode={mode}
                showPrintMargin={true}
                showGutter={true}
                highlightActiveLine={true}
                onChange={changeHandler}
                editorProps={{
                    $blockScrolling: true,
                }}
                value={value}
                setOptions={{
                    enableBasicAutocompletion: true,
                    enableLiveAutocompletion: true,
                    enableSnippets: true,
                    showLineNumbers: true,
                    tabSize: 2,
                    useWorker: false,
                }}
                width={'100%'}
                height={'300px'}
                {...rest}
            />
        </div>
    );
}
