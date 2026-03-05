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
            TextStyleKit,
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
            Link.configure({
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
