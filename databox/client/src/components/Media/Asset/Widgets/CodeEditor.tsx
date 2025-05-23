import React from 'react';
import AceEditor, {IAceEditorProps} from 'react-ace';
import 'ace-builds/src-noconflict/theme-monokai';
import 'ace-builds/src-noconflict/ext-language_tools';
import 'ace-builds/src-noconflict/mode-yaml';
import 'ace-builds/src-noconflict/mode-json';

type Props = {
    prettify?: (code: string) => string;
} & IAceEditorProps;

export type {Props as CodeEditorProps};

export default function CodeEditor({
    value: initialValue,
    onChange,
    prettify,
    mode,
    ...rest
}: Props) {
    const [value, setValue] = React.useState<string | undefined>();

    React.useEffect(() => {
        try {
            if (initialValue) {
                setValue(prettify ? prettify(initialValue) : initialValue);
            }
        } catch (e) {
            setValue(initialValue);
        }
    }, [initialValue]);

    const changeHandler = React.useCallback(
        (value: string) => {
            onChange && onChange(value);
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
                theme="monokai"
                fontSize={15}
                mode={mode}
                showPrintMargin={true}
                showGutter={true}
                highlightActiveLine={true}
                onChange={changeHandler}
                editorProps={{$blockScrolling: true}}
                value={value}
                setOptions={{
                    enableBasicAutocompletion: false,
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
