import {TextStyleKit} from '@tiptap/extension-text-style';
import {useEditor, EditorContent, EditorContext} from '@tiptap/react';
import {FloatingMenu, BubbleMenu} from '@tiptap/react/menus';
import StarterKit from '@tiptap/starter-kit';
import './styles.scss';
import {MenuBar} from './MenuBar.tsx';
import {useMemo} from 'react';
import Preview from './Preview.tsx';
import {ColorHighlighterExtension} from './extensions/highlighter/extension.ts';
import './styles.scss';
import InsertMenu from './InsertMenu.tsx';
import DragHandle from '@tiptap/extension-drag-handle-react';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';
import {WidgetExtension} from './extensions/widgets/extension.ts';

const extensions = [
    TextStyleKit,
    StarterKit,
    ColorHighlighterExtension,
    WidgetExtension,
];

type Props = {};
export default function LandingEditor({}: Props) {
    const editor = useEditor({
        extensions,
        content: `<h2>Landing Editor</h2>`,
    });

    const providerValue = useMemo(() => ({editor}), [editor]);

    if (!editor) {
        return null;
    }

    return (
        <>
            <EditorContext.Provider value={providerValue}>
                <MenuBar editor={editor} />
                <DragHandle
                    editor={editor}
                    nested={{edgeDetection: {threshold: -16}}}
                >
                    <div>
                        <DragIndicatorIcon />
                    </div>
                </DragHandle>
                <EditorContent editor={editor} />
                <FloatingMenu editor={editor}>
                    <InsertMenu editor={editor} />
                </FloatingMenu>
                <BubbleMenu editor={editor}>This is the bubble menu</BubbleMenu>
                <Preview />
            </EditorContext.Provider>
        </>
    );
}
