/**
 * Styles shared by posted messages (`FormattedMessage`) and the WYSIWYG
 * composer (`MessageEditor`), so that a message looks the same while typed
 * and once sent.
 */
export const messageContentClass = [
    'break-words',
    '[&>*+*]:mt-1.5',
    '[&_p]:whitespace-pre-wrap',
    '[&_ul]:list-disc [&_ul]:space-y-0.5 [&_ul]:pl-5',
    '[&_ol]:list-decimal [&_ol]:space-y-0.5 [&_ol]:pl-5',
    '[&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-2 [&_blockquote]:whitespace-pre-wrap [&_blockquote]:text-muted-foreground',
    '[&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:px-2 [&_pre]:py-1.5 [&_pre]:font-mono [&_pre]:text-xs',
    '[&_:not(pre)>code]:rounded [&_:not(pre)>code]:bg-muted [&_:not(pre)>code]:px-1 [&_:not(pre)>code]:py-0.5 [&_:not(pre)>code]:font-mono [&_:not(pre)>code]:text-[0.85em]',
    '[&_strong]:font-semibold',
    '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2 [&_a:hover]:no-underline',
].join(' ');

export function mentionChipClass(self?: boolean): string {
    return `inline-block rounded-full px-1.5 leading-snug font-medium whitespace-nowrap ${
        self
            ? 'bg-primary text-primary-foreground'
            : 'bg-primary/15 text-primary'
    }`;
}
