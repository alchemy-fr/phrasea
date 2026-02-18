import {EditorContent, EditorContext, useEditor} from '@tiptap/react';
import {BubbleMenu, FloatingMenu} from '@tiptap/react/menus';
import './styles.scss';
import {MenuBar, MenuBarOptions} from './MenuBar.tsx';
import {useCallback, useEffect, useMemo, useState} from 'react';
import DragHandle from '@tiptap/extension-drag-handle-react';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';
import {WidgetConstants} from './extensions/widgets/extension.ts';
import {Box} from '@mui/material';
import AddMenu from './menu/AddMenu.tsx';
import WidgetBubbleMenu from './WidgetBubbleMenu.tsx';
import {PageContent} from '../../../types.ts';
import {extensions} from './extensions.ts';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';

export type OnPageSave = (content: PageContent) => void;

type Props = {
    onSave: OnPageSave;
} & MenuBarOptions;

export default function PageEditor({data, onSave, ...menuProps}: Props) {
    const [changed, setChanged] = useState(false);

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
                            text: data?.title ?? 'Page',
                        },
                    ],
                },
            ],
        },
    });

    const providerValue = useMemo(() => ({editor}), [editor]);

    useEffect(() => {
        if (!editor) {
            return;
        }
        const onUpdate = () => {
            setChanged(true);
        };
        editor.on('update', onUpdate);

        return () => {
            editor.off('update', onUpdate);
        };
    }, [editor]);

    useDirtyFormPrompt(changed);

    const saveHandler = useCallback<OnPageSave>(
        content => {
            if (!editor) {
                return;
            }
            onSave?.(content);
            setChanged(false);
        },
        [editor, onSave]
    );

    if (!editor) {
        return null;
    }

    return (
        <>
            <EditorContext.Provider value={providerValue}>
                <MenuBar
                    hasChanged={changed}
                    data={data}
                    editor={editor}
                    onSave={saveHandler}
                    {...menuProps}
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
