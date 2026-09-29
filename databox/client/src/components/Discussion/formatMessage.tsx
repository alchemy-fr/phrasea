import {Fragment, ReactNode} from 'react';
import {styled} from '@mui/material/styles';
import {alpha, Theme} from '@mui/material';
import {BlockNode, InlineNode, parseMessage} from './messageMarkup.ts';

/**
 * Renders a discussion message (see `messageMarkup.ts` for the format, shared
 * with the Next.js client): mentions as tags, URLs as links, markdown basics,
 * line breaks preserved. Only React elements are produced.
 */
export function formatMessage(value?: string): ReactNode {
    if (!value) {
        return null;
    }

    return (
        <MessageBody>
            {parseMessage(value).map((b, i) => (
                <Fragment key={i}>{renderBlock(b)}</Fragment>
            ))}
        </MessageBody>
    );
}

function renderBlock(block: BlockNode): ReactNode {
    switch (block.type) {
        case 'paragraph':
            return <p>{renderInline(block.children)}</p>;
        case 'quote':
            return <blockquote>{renderInline(block.children)}</blockquote>;
        case 'codeblock':
            return (
                <pre>
                    <code>{block.text}</code>
                </pre>
            );
        case 'list': {
            const items = block.items.map((item, j) => (
                <li key={j}>{renderInline(item)}</li>
            ));

            return block.ordered ? (
                <ol start={block.start !== 1 ? block.start : undefined}>
                    {items}
                </ol>
            ) : (
                <ul>{items}</ul>
            );
        }
    }
}

function renderInline(nodes: InlineNode[]): ReactNode {
    return nodes.map((n, i) => {
        switch (n.type) {
            case 'text':
                return <Fragment key={i}>{n.text}</Fragment>;
            case 'br':
                return <br key={i} />;
            case 'mention':
                return <UserTag key={i}>@{n.username}</UserTag>;
            case 'link':
                return (
                    <a
                        key={i}
                        href={n.href}
                        title={n.href}
                        target="_blank"
                        rel="noreferrer"
                    >
                        {n.label === n.href ? truncateUrl(n.href, 50) : n.label}
                    </a>
                );
            case 'code':
                return <code key={i}>{n.text}</code>;
            case 'strong':
                return <strong key={i}>{renderInline(n.children)}</strong>;
            case 'em':
                return <em key={i}>{renderInline(n.children)}</em>;
            case 'del':
                return <del key={i}>{renderInline(n.children)}</del>;
        }
    });
}

export const createUserTagStyle = (theme: Theme) => ({
    backgroundColor: alpha(theme.palette.primary.main, 0.1),
    padding: '1px 1px',
    margin: '-1px -1px',
    borderRadius: 4,
});

const UserTag = styled('span')(({theme}) => createUserTagStyle(theme));

const MessageBody = styled('div')(({theme}) => ({
    'overflowWrap': 'anywhere',
    '& p, & blockquote, & pre, & ul, & ol': {
        margin: 0,
        whiteSpace: 'pre-wrap',
    },
    '& > * + *': {
        marginTop: theme.spacing(0.75),
    },
    '& ul, & ol': {
        paddingLeft: theme.spacing(2.5),
    },
    '& blockquote': {
        borderLeft: `2px solid ${theme.palette.divider}`,
        paddingLeft: theme.spacing(1),
        color: theme.palette.text.secondary,
    },
    '& code': {
        fontFamily: 'monospace',
        fontSize: '0.85em',
        backgroundColor: theme.palette.action.hover,
        borderRadius: 4,
        padding: '1px 4px',
    },
    '& pre': {
        'overflowX': 'auto',
        'backgroundColor': theme.palette.action.hover,
        'borderRadius': 4,
        'padding': theme.spacing(0.75, 1),
        '& code': {
            backgroundColor: 'transparent',
            padding: 0,
        },
    },
}));

function truncateUrl(url: string, maxLength: number): string {
    if (url.length <= maxLength) return url;

    const keepLength = Math.floor(maxLength / 2) - 2; // Keeping both start and end parts
    const start = url.slice(0, keepLength);
    const end = url.slice(-keepLength);

    return `${start}…${end}`;
}
