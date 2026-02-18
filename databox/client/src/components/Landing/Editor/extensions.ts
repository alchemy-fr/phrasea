import {ColorHighlighterExtension} from './extensions/highlighter/extension.ts';
import {WidgetExtension} from './extensions/widgets/extension.ts';
import StarterKit from '@tiptap/starter-kit';
import HorizontalRule from '@tiptap/extension-horizontal-rule';
import TextAlign from '@tiptap/extension-text-align';
import {TaskItem, TaskList} from '@tiptap/extension-list';
import Highlight from '@tiptap/extension-highlight';
import TypographyExtension from '@tiptap/extension-typography';
import Superscript from '@tiptap/extension-superscript';
import Subscript from '@tiptap/extension-subscript';

export const extensions = [
    ColorHighlighterExtension,
    WidgetExtension,
    StarterKit.configure({
        horizontalRule: false,
        link: {
            openOnClick: false,
            enableClickSelection: true,
        },
    }),
    HorizontalRule,
    TextAlign.configure({types: ['heading', 'paragraph']}),
    TaskList,
    TaskItem.configure({nested: true}),
    Highlight.configure({multicolor: true}),
    TypographyExtension,
    Superscript,
    Subscript,
];
