import {EditorContent, EditorContext, useEditor} from '@tiptap/react';
import {FloatingMenu} from '@tiptap/react/menus';
import './styles.scss';
import {MenuBar, MenuBarOptions} from './MenuBar.tsx';
import {useCallback, useEffect, useMemo, useState} from 'react';
import DragHandle from '@tiptap/extension-drag-handle-react';
import DragIndicatorIcon from '@mui/icons-material/DragIndicator';
import AddMenu from './menu/AddMenu.tsx';
import {PageContent} from '../../../types.ts';
import {useExtensions} from './extensions.ts';
import {useTranslation} from 'react-i18next';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {toast} from 'react-toastify';
import PageWrapper from '../PageWrapper.tsx';
import {Box} from '@mui/material';

export type OnPageSave = (content: PageContent) => void;

type Props = {
    onSave: OnPageSave;
} & MenuBarOptions;

export default function PageEditor({data, onSave, ...menuProps}: Props) {
    const {t} = useTranslation();
    const [changed, setChanged] = useState(false);

    const extensions = useExtensions({editing: true});

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
            toast.success(
                t('landing.page_editor.save_success', 'Page saved successfully')
            );
            setChanged(false);
        },
        [editor, onSave, t, setChanged]
    );

    if (!editor) {
        return null;
    }

    return (
        <Box
            sx={{
                height: '100vh',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <EditorContext.Provider value={providerValue}>
                <MenuBar
                    hasChanged={changed}
                    data={data}
                    editor={editor}
                    onSave={saveHandler}
                    {...menuProps}
                />
                <PageWrapper
                    sx={{
                        flexGrow: 1,
                        overflow: 'auto',
                    }}
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
                </PageWrapper>
            </EditorContext.Provider>
        </Box>
    );
}
