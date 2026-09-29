import {Fragment, ReactNode, useMemo} from 'react';
import {truncate} from '@/lib/utils/format';
import {BlockNode, InlineNode, parseMessage} from './messageMarkup';
import {mentionChipClass, messageContentClass} from './messageStyles';

/**
 * Renders a discussion message (see `messageMarkup` for the format): mentions
 * as pills (highlighted when they designate the current user), URLs as links,
 * markdown basics, line breaks preserved. Only React elements are produced:
 * the content is never injected as HTML.
 */
export function FormattedMessage({
    content,
    currentUserId,
}: {
    content: string;
    currentUserId?: string;
}): ReactNode {
    const blocks = useMemo(() => parseMessage(content), [content]);

    return (
        <div className={messageContentClass}>
            {blocks.map((b, i) => (
                <Fragment key={i}>{renderBlock(b, currentUserId)}</Fragment>
            ))}
        </div>
    );
}

function renderBlock(block: BlockNode, currentUserId?: string): ReactNode {
    const inline = (nodes: InlineNode[]) => renderInline(nodes, currentUserId);

    switch (block.type) {
        case 'paragraph':
            return <p>{inline(block.children)}</p>;
        case 'quote':
            return <blockquote>{inline(block.children)}</blockquote>;
        case 'codeblock':
            return (
                <pre>
                    <code>{block.text}</code>
                </pre>
            );
        case 'list': {
            const items = block.items.map((item, j) => (
                <li key={j}>{inline(item)}</li>
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

function renderInline(nodes: InlineNode[], currentUserId?: string): ReactNode {
    return nodes.map((n, i) => {
        switch (n.type) {
            case 'text':
                return <Fragment key={i}>{n.text}</Fragment>;
            case 'br':
                return <br key={i} />;
            case 'mention':
                return (
                    <span
                        key={i}
                        data-mention={n.username}
                        className={mentionChipClass(
                            !!currentUserId && n.id === currentUserId
                        )}
                    >
                        @{n.username}
                    </span>
                );
            case 'link':
                return (
                    <a
                        key={i}
                        href={n.href}
                        title={n.href}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {n.label === n.href ? truncate(n.href, 50) : n.label}
                    </a>
                );
            case 'code':
                return <code key={i}>{n.text}</code>;
            case 'strong':
                return (
                    <strong key={i}>
                        {renderInline(n.children, currentUserId)}
                    </strong>
                );
            case 'em':
                return (
                    <em key={i}>{renderInline(n.children, currentUserId)}</em>
                );
            case 'del':
                return (
                    <del key={i}>{renderInline(n.children, currentUserId)}</del>
                );
        }
    });
}
