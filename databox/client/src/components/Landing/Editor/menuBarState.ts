import type {Editor} from '@tiptap/core';
import type {EditorStateSnapshot} from '@tiptap/react';
import {TextAlignEnum} from './editorTypes.ts';
import {isNodeTypeSelected} from './editorUtils.ts';
import {LinkAttributes} from './extensions/link/types.ts';

export type BlockType =
    | 'paragraph'
    | 'heading1'
    | 'heading2'
    | 'heading3'
    | 'heading4'
    | 'heading5'
    | 'heading6';

export function menuBarStateSelector(ctx: EditorStateSnapshot<Editor>) {
    const editor = ctx.editor;
    const textStyle = editor.getAttributes('textStyle');

    return {
        // Text formatting
        isBold: editor.isActive('bold') ?? false,
        canBold: editor.can().chain().toggleBold().run() ?? false,
        isItalic: editor.isActive('italic') ?? false,
        canItalic: editor.can().chain().toggleItalic().run() ?? false,
        isUnderline: editor.isActive('underline') ?? false,
        canUnderline: editor.can().chain().toggleUnderline().run() ?? false,
        isStrike: editor.isActive('strike') ?? false,
        canStrike: editor.can().chain().toggleStrike().run() ?? false,
        isCode: editor.isActive('code') ?? false,
        canCode: editor.can().chain().toggleCode().run() ?? false,
        canClearMarks: editor.can().chain().unsetAllMarks().run() ?? false,

        // Block types
        currentBlockType: (editor.isActive('paragraph')
            ? 'paragraph'
            : editor.isActive('heading', {level: 1})
              ? 'heading1'
              : editor.isActive('heading', {level: 2})
                ? 'heading2'
                : editor.isActive('heading', {level: 3})
                  ? 'heading3'
                  : editor.isActive('heading', {level: 4})
                    ? 'heading4'
                    : editor.isActive('heading', {level: 5})
                      ? 'heading5'
                      : editor.isActive('heading', {level: 6})
                        ? 'heading6'
                        : null) as BlockType | null,
        canSetParagraph:
            editor.can().chain().setParagraph().run() ||
            editor.isActive('paragraph') ||
            false,

        // Text styles
        canSetFontSize: editor.can().chain().setFontSize('16px').run() ?? false,
        currentFontSize: textStyle.fontSize || null,
        canSetColor: editor.can().chain().setColor('#000000').run() ?? false,
        currentColor: textStyle.color || '#000000',

        // Lists and blocks
        isBulletList: editor.isActive('bulletList') ?? false,
        isOrderedList: editor.isActive('orderedList') ?? false,
        isCodeBlock: editor.isActive('codeBlock') ?? false,
        isBlockquote: editor.isActive('blockquote') ?? false,

        // Link
        isLink: editor.isActive('link') ?? false,
        canSetLink:
            editor
                .can()
                .chain()
                .toggleLink({href: 'https://example.com'})
                .run() ?? false,
        currentLinkSpec: (editor.getAttributes('link') ??
            null) as LinkAttributes | null,

        // Font
        canSetFontFamily:
            editor.can().chain().setFontFamily('Arial').run() ?? false,
        currentFontFamily: textStyle.fontFamily || '',

        // History
        canUndo: editor.can().chain().undo().run() ?? false,
        canRedo: editor.can().chain().redo().run() ?? false,

        isTextAlign: (textAlign: TextAlignEnum) =>
            editor.isActive({textAlign}) ?? false,

        canSetTextAlign: (textAlign: TextAlignEnum) =>
            canSetTextAlign(editor, textAlign),
    };
}

export type MenuBarState = ReturnType<typeof menuBarStateSelector>;

function canSetTextAlign(editor: Editor, align: TextAlignEnum): boolean {
    if (isNodeTypeSelected(editor, ['image', 'horizontalRule'])) {
        return false;
    }

    return editor.can().setTextAlign(align);
}
