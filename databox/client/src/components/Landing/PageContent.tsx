import {EditorContent, useEditor} from '@tiptap/react';
import {extensions} from './Editor/extensions.ts';
import {Page} from '../../types.ts';
import {Box, Container} from '@mui/material';
import {Helmet} from 'react-helmet';

type Props = {
    data: Page;
};

export default function PageContent({data}: Props) {
    const editor = useEditor({
        immediatelyRender: false,
        editable: false,
        extensions,
        content: data?.data ?? '',
    });

    return (
        <>
            <Helmet>
                <title>{data.title}</title>
                {data.description ? (
                    <meta name="description" content={data.description} />
                ) : null}
            </Helmet>
            <Container>
                <Box
                    sx={{
                        '[contenteditable="false"]:focus': {
                            outline: 'none',
                        },
                    }}
                >
                    <EditorContent
                        editor={editor}
                        contentEditable={false}
                        selected={false}
                        disabled={true}
                    />
                </Box>
            </Container>
        </>
    );
}
