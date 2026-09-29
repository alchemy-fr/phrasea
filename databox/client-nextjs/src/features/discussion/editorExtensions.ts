import {Extension, Extensions, mergeAttributes, Node} from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import {mentionChipClass} from './messageStyles';

/**
 * `@user` mention, stored as `@[username](userId)` (see `messageDoc`) and
 * displayed as the same pill as in posted messages.
 */
export const MentionNode = Node.create({
    name: 'mention',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: false,

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: el => el.getAttribute('data-id'),
                renderHTML: attrs => ({'data-id': attrs.id}),
            },
            username: {
                default: '',
                parseHTML: el => el.getAttribute('data-mention'),
                renderHTML: attrs => ({'data-mention': attrs.username}),
            },
        };
    },

    parseHTML() {
        return [{tag: 'span[data-mention][data-id]'}];
    },

    renderHTML({node, HTMLAttributes}) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, {class: mentionChipClass()}),
            `@${node.attrs.username}`,
        ];
    },

    renderText({node}) {
        return `@${node.attrs.username}`;
    },
});

/**
 * Schema of the composer: only what the stored format can express (no
 * heading, rule nor underline; http(s) links only).
 */
export function messageExtensions(): Extensions {
    return [
        StarterKit.configure({
            heading: false,
            horizontalRule: false,
            underline: false,
            link: {
                openOnClick: false,
                autolink: true,
                linkOnPaste: true,
                defaultProtocol: 'https',
                protocols: ['http', 'https'],
                isAllowedUri: url => /^https?:\/\//i.test(url),
                HTMLAttributes: {
                    rel: 'noopener noreferrer',
                    target: '_blank',
                },
            },
        }),
        MentionNode,
    ];
}

/**
 * Enter sends, Shift+Enter breaks the line: a new list item or quote line
 * (leaving the list / quote from an empty one), a line break elsewhere.
 * Ctrl+Enter still sends.
 */
export const MessageKeymap = Extension.create<{onSubmit: () => void}>({
    name: 'messageKeymap',
    // Before the list / code block Enter handling
    priority: 1000,

    addOptions() {
        return {onSubmit: () => {}};
    },

    addKeyboardShortcuts() {
        const submit = () => {
            this.options.onSubmit();

            return true;
        };

        return {
            'Enter': submit,
            'Mod-Enter': submit,
            'Shift-Enter': ({editor}) =>
                editor.commands.first(({commands}) => [
                    () => commands.newlineInCode(),
                    () =>
                        editor.isActive('listItem') &&
                        (commands.splitListItem('listItem') ||
                            commands.liftListItem('listItem')),
                    () =>
                        editor.isActive('blockquote') &&
                        (editor.state.selection.$from.parent.content.size === 0
                            ? commands.lift('blockquote')
                            : commands.splitBlock()),
                    () => commands.setHardBreak(),
                ]),
        };
    },
});
