import {EditorContent, useEditor} from '@tiptap/react';
import {extensions} from './Editor/extensions.ts';
import {Page} from '../../types.ts';
import {Container} from '@mui/material';

type Props = {
    data: Page;
};

export default function PageContent({data}: Props) {
    const editor = useEditor({
        immediatelyRender: true,
        editable: false,
        extensions,
        content: data?.data ?? '',
    });

    return (
        <>
            <Container>
                <EditorContent editor={editor} />
            </Container>
        </>
    );
}
