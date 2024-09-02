import AceEditor, {IAceEditorProps} from 'react-ace';
import 'ace-builds/src-noconflict/theme-monokai';
import 'ace-builds/src-noconflict/ext-language_tools';
import React from 'react';

type Props = {
    prettify?: (code: string) => string;
} & IAceEditorProps;

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
    }, []);

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
                style={{
                    width: '100%',
                    height: 200,
                }}
                setOptions={{
                    enableBasicAutocompletion: false,
                    enableLiveAutocompletion: true,
                    enableSnippets: true,
                    showLineNumbers: true,
                    tabSize: 2,
                }}
                {...rest}
            />
        </div>
    );
}
