import {afterEach, describe, expect, it, vi} from 'vitest';
import {Editor} from '@tiptap/core';
import {MessageKeymap, messageExtensions} from './editorExtensions';
import {docToMarkup, markupToDoc} from './messageDoc';

let editor: Editor | undefined;

function setup(markup: string) {
    const onSubmit = vi.fn();
    editor = new Editor({
        element: document.createElement('div'),
        extensions: [
            ...messageExtensions(),
            MessageKeymap.configure({onSubmit}),
        ],
        content: markupToDoc(markup),
    });
    editor.commands.focus('end');

    return {
        editor,
        onSubmit,
        markup: () => docToMarkup(editor!.getJSON()),
    };
}

afterEach(() => {
    editor?.destroy();
    editor = undefined;
});

describe('MessageKeymap', () => {
    it('sends on Enter and Ctrl+Enter', () => {
        const {editor, onSubmit, markup} = setup('- a');
        editor.commands.keyboardShortcut('Enter');
        editor.commands.keyboardShortcut('Mod-Enter');
        expect(onSubmit).toHaveBeenCalledTimes(2);
        expect(markup()).toBe('- a');
    });

    it('breaks the line on Shift+Enter', () => {
        const {editor, onSubmit, markup} = setup('a');
        editor.commands.keyboardShortcut('Shift-Enter');
        editor.commands.insertContent('b');
        expect(markup()).toBe('a\nb');
        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('adds a list item on Shift+Enter, leaves the list from an empty one', () => {
        const {editor, markup} = setup('- a');
        editor.commands.keyboardShortcut('Shift-Enter');
        editor.commands.insertContent('b');
        expect(markup()).toBe('- a\n- b');
        editor.commands.keyboardShortcut('Shift-Enter');
        editor.commands.keyboardShortcut('Shift-Enter');
        editor.commands.insertContent('c');
        expect(markup()).toBe('- a\n- b\nc');
    });

    it('adds a quote line on Shift+Enter, leaves the quote from an empty one', () => {
        const {editor, markup} = setup('> a');
        editor.commands.keyboardShortcut('Shift-Enter');
        editor.commands.insertContent('b');
        expect(markup()).toBe('> a\n> b');
        editor.commands.keyboardShortcut('Shift-Enter');
        editor.commands.keyboardShortcut('Shift-Enter');
        editor.commands.insertContent('c');
        expect(markup()).toBe('> a\n> b\nc');
    });

    it('applies markdown input rules', () => {
        const {editor, markup} = setup('');
        // Input rules run on typed text only
        const type = (text: string) => {
            for (const ch of text) {
                const {from, to} = editor.state.selection;
                const handled = editor.view.someProp('handleTextInput', f =>
                    f(editor.view, from, to, ch, () =>
                        editor.state.tr.insertText(ch, from, to)
                    )
                );
                if (!handled) {
                    editor.commands.insertContent(ch);
                }
            }
        };
        type('**bold** ');
        expect(markup()).toBe('**bold**');
    });
});
