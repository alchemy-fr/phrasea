import {EditorContent, EditorContext, useEditor} from '@tiptap/react';
import {BubbleMenu, FloatingMenu} from '@tiptap/react/menus';
import './styles.scss';
import {MenuBar} from './MenuBar.tsx';
import {useMemo} from 'react';
import DragHandle from '@tiptap/extension-drag-handle-react';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';
import {WidgetConstants} from './extensions/widgets/extension.ts';
import {Box} from '@mui/material';
import AddMenu from './menu/AddMenu.tsx';
import WidgetBubbleMenu from './WidgetBubbleMenu.tsx';
import {Page, PageContent} from '../../../types.ts';
import {extensions} from './extensions.ts';

export type OnPageSave = (content: PageContent) => void;

type Props = {
    data?: Page;
    onSave: OnPageSave;
    onPreview?: () => void;
};

export default function PageEditor({data, onSave, onPreview}: Props) {
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
        content: data?.data ?? {
            type: 'doc',
            content: [
                {
                    type: 'heading',
                    attrs: {level: 1},
                    content: [
                        {
                            type: 'text',
                            text: 'Page',
                        },
                    ],
                },
            ],
        },
    });

    const providerValue = useMemo(() => ({editor}), [editor]);

    if (!editor) {
        return null;
    }

    return (
        <>
            <EditorContext.Provider value={providerValue}>
                <MenuBar
                    editor={editor}
                    onSave={onSave}
                    onPreview={onPreview}
                />
                <Box
                    sx={_theme => ({
                        p: 2,
                        mb: 4,
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
                        <AddMenu editor={editor} />
                    </FloatingMenu>
                    <BubbleMenu
                        editor={editor}
                        shouldShow={() => editor.isActive(WidgetConstants.Type)}
                        options={{placement: 'top-start', offset: 8}}
                    >
                        <WidgetBubbleMenu editor={editor} />
                    </BubbleMenu>
                </Box>
            </EditorContext.Provider>
        </>
    );
}
