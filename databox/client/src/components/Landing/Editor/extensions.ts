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
import {useMemo} from 'react';
import {TextStyleKit} from '@tiptap/extension-text-style';
import Link from '@tiptap/extension-link';

export function useExtensions({editing}: {editing: boolean}) {
    return useMemo(() => {
        return [
            ColorHighlighterExtension,
            WidgetExtension.configure({
                editing,
            }),
            TextStyleKit.configure(),
            StarterKit.configure({
                paragraph: {
                    HTMLAttributes: {class: 'landing-paragraph'},
                },
                horizontalRule: {
                    HTMLAttributes: {class: 'landing-horizontal-rule'},
                },
                link: false,
                orderedList: {
                    HTMLAttributes: {class: 'landing-ordered-list'},
                },
                bulletList: {
                    HTMLAttributes: {class: 'landing-bullet-list'},
                },
                blockquote: {
                    HTMLAttributes: {class: 'landing-blockquote'},
                },
                code: {
                    HTMLAttributes: {class: 'landing-code'},
                },
                codeBlock: {
                    HTMLAttributes: {class: 'landing-code-block'},
                },
                heading: {
                    HTMLAttributes: {
                        class: `landing-heading`,
                    },
                },
                listItem: {
                    HTMLAttributes: {class: 'landing-list-item'},
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
            Link.configure({
                HTMLAttributes: {class: 'landing-link'},
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
                protocols: ['http', 'https'],
                isAllowedUri: (url, ctx) => {
                    try {
                        // construct URL
                        const parsedUrl = url.includes(':')
                            ? new URL(url)
                            : new URL(`${ctx.defaultProtocol}://${url}`);

                        // use default validation
                        if (!ctx.defaultValidate(parsedUrl.href)) {
                            return false;
                        }

                        // disallowed protocols
                        const disallowedProtocols = ['ftp', 'file', 'mailto'];
                        const protocol = parsedUrl.protocol.replace(':', '');

                        if (disallowedProtocols.includes(protocol)) {
                            return false;
                        }

                        // only allow protocols specified in ctx.protocols
                        const allowedProtocols = ctx.protocols.map(p =>
                            typeof p === 'string' ? p : p.scheme
                        );

                        if (!allowedProtocols.includes(protocol)) {
                            return false;
                        }

                        // all checks have passed
                        return true;
                    } catch {
                        return false;
                    }
                },
                shouldAutoLink: url => {
                    try {
                        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
                        url.includes(':')
                            ? new URL(url)
                            : new URL(`https://${url}`);

                        return true;
                    } catch {
                        return false;
                    }
                },
            }),
        ];
    }, [editing]);
}
