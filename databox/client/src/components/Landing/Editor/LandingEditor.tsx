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
import {Box} from '@mui/material';
import TextAlign from '@tiptap/extension-text-align';
import HorizontalRule from '@tiptap/extension-horizontal-rule';
import {TaskItem, TaskList} from '@tiptap/extension-list';
import Superscript from '@tiptap/extension-superscript';
import Subscript from '@tiptap/extension-subscript';
import TypographyExtension from '@tiptap/extension-typography';
import Highlight from '@tiptap/extension-highlight';

const extensions = [
    ColorHighlighterExtension,
    WidgetExtension,
    StarterKit.configure({
        horizontalRule: false,
        link: {
            openOnClick: false,
            enableClickSelection: true,
        },
    }),
    HorizontalRule,
    TextAlign.configure({types: ['heading', 'paragraph']}),
    TaskList,
    TaskItem.configure({nested: true}),
    Highlight.configure({multicolor: true}),
    TypographyExtension,
    Superscript,
    Subscript,
];

type Props = {};

export default function LandingEditor({}: Props) {
    const editor = useEditor({
        immediatelyRender: false,
        editorProps: {
            attributes: {
                'autocomplete': 'off',
                'autocorrect': 'off',
                'autocapitalize': 'off',
                'aria-label': 'Main content area, start typing to enter text.',
            },
        },
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
                <Box
                    sx={theme => ({
                        p: 2,
                        mb: 4,
                        border: `1px solid ${theme.palette.divider}`,
                    })}
                >
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
                    <BubbleMenu editor={editor}>
                        This is the bubble menu
                    </BubbleMenu>
                </Box>

                <Preview />
            </EditorContext.Provider>
        </>
    );
}
