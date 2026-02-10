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

const extensions = [TextStyleKit, StarterKit, ColorHighlighterExtension];

type Props = {};
export default function LandingEditor({}: Props) {
    const editor = useEditor({
        extensions,
        content: `
<h2>
  Hi there,
</h2>
<p>
  this is a <em>basic</em> example of <strong>Tiptap</strong>. Sure, there are all kind of basic text styles you'd probably expect from a text editor. But wait until you see the lists:
</p>
<ul>
  <li>
    That's a bullet list with one …
  </li>
  <li>
    … or two list items.
  </li>
</ul>
<p>
  Isn't that great? And all of that is editable. But wait, there's more. Let's try a code block:
</p>
<pre><code class="language-css">body {
  display: none;
}</code></pre>
<p>
  I know, I know, this is impressive. It's only the tip of the iceberg though. Give it a try and click a little bit around. Don't forget to check the other examples too.
</p>
<blockquote>
  Wow, that's amazing. Good work, boy! 👏
  <br />
  — Mom
</blockquote>
`,
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
                    <InsertMenu />
                </FloatingMenu>
                <BubbleMenu editor={editor}>This is the bubble menu</BubbleMenu>
                <Preview />
            </EditorContext.Provider>
        </>
    );
}
