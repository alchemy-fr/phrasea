import {useCurrentEditor, useEditorState} from '@tiptap/react';

type Props = {};

export default function Preview({}: Props) {
    const {editor} = useCurrentEditor();

    const editorState = useEditorState({
        editor,

        // the selector function is used to select the state you want to react to
        selector: ({editor}) => {
            if (!editor) return null;

            return {
                isEditable: editor.isEditable,
                currentSelection: editor.state.selection,
                currentContent: editor.getJSON(),
            };
        },
    });

    return (
        <>
            <pre
                style={{
                    whiteSpace: 'pre-wrap',
                    fontSize: '0.75rem',
                }}
            >
                {JSON.stringify(editorState?.currentContent, null, 2)}
            </pre>
        </>
    );
}
