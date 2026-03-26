import {EditorContent, useEditor} from '@tiptap/react';
import {useExtensions} from './Editor/extensions.ts';
import {Page} from '../../types.ts';
import {Helmet} from 'react-helmet';
import PageWrapper from './PageWrapper.tsx';

type Props = {
    data: Page;
};

export default function PageContent({data}: Props) {
    const extensions = useExtensions({editing: false});

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
            <PageWrapper>
                <EditorContent
                    editor={editor}
                    contentEditable={false}
                    selected={false}
                    disabled={true}
                />
            </PageWrapper>
        </>
    );
}
