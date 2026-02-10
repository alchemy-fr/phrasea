import type {Editor} from '@tiptap/core';
import type {EditorStateSnapshot} from '@tiptap/react';

/**
 * State selector for the MenuBar component.
 * Extracts the relevant editor state for rendering menu buttons.
 */
export function menuBarStateSelector(ctx: EditorStateSnapshot<Editor>) {
    const editor = ctx.editor;

    return {
        // Text formatting
        isBold: editor.isActive('bold') ?? false,
        canBold: editor.can().chain().toggleBold().run() ?? false,
        isItalic: editor.isActive('italic') ?? false,
        canItalic: editor.can().chain().toggleItalic().run() ?? false,
        isStrike: editor.isActive('strike') ?? false,
        canStrike: editor.can().chain().toggleStrike().run() ?? false,
        isCode: editor.isActive('code') ?? false,
        canCode: editor.can().chain().toggleCode().run() ?? false,
        canClearMarks: editor.can().chain().unsetAllMarks().run() ?? false,

        // Block types
        isParagraph: editor.isActive('paragraph') ?? false,
        isHeading1: editor.isActive('heading', {level: 1}) ?? false,
        isHeading2: editor.isActive('heading', {level: 2}) ?? false,
        isHeading3: editor.isActive('heading', {level: 3}) ?? false,
        isHeading4: editor.isActive('heading', {level: 4}) ?? false,
        isHeading5: editor.isActive('heading', {level: 5}) ?? false,
        isHeading6: editor.isActive('heading', {level: 6}) ?? false,

        // Lists and blocks
        isBulletList: editor.isActive('bulletList') ?? false,
        isOrderedList: editor.isActive('orderedList') ?? false,
        isCodeBlock: editor.isActive('codeBlock') ?? false,
        isBlockquote: editor.isActive('blockquote') ?? false,

        // History
        canUndo: editor.can().chain().undo().run() ?? false,
        canRedo: editor.can().chain().redo().run() ?? false,
    };
}

export type MenuBarState = ReturnType<typeof menuBarStateSelector>;
