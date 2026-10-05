'use client';

import {FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {Editor} from '@tiptap/core';
import {useEditorState} from '@tiptap/react';
import {LinkIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
    Tooltip,
} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';

/** Adds, changes or removes the link of the selection (http(s) only). */
export function LinkButton({editor}: {editor: Editor | null}) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);
    const [url, setUrl] = useState('');
    const active = useEditorState({
        editor,
        selector: ({editor}) => !!editor?.isActive('link'),
    });
    const label = t('discussion.format.link', 'Link');

    const apply = (e: FormEvent) => {
        e.preventDefault();
        setOpen(false);
        if (!editor) {
            return;
        }
        const value = url.trim();
        const chain = editor.chain().focus().extendMarkRange('link');
        if (!value) {
            chain.unsetLink().run();

            return;
        }
        const href = /^https?:\/\//i.test(value) ? value : `https://${value}`;
        if (editor.state.selection.empty && !active) {
            chain
                .insertContent([
                    {
                        type: 'text',
                        text: href,
                        marks: [{type: 'link', attrs: {href}}],
                    },
                    {type: 'text', text: ' '},
                ])
                .run();
        } else {
            chain.setLink({href}).run();
        }
    };

    return (
        <Popover
            open={open}
            onOpenChange={o => {
                setOpen(o);
                if (o) {
                    setUrl(editor?.getAttributes('link').href ?? '');
                }
            }}
        >
            <Tooltip content={label}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        aria-label={label}
                        aria-pressed={!!active}
                        disabled={!editor}
                        className={cn(
                            active && 'bg-accent text-accent-foreground'
                        )}
                        onMouseDown={e => e.preventDefault()}
                    >
                        <LinkIcon />
                    </Button>
                </PopoverTrigger>
            </Tooltip>
            <PopoverContent
                align="start"
                className="w-80 p-2"
                onCloseAutoFocus={e => {
                    e.preventDefault();
                    editor?.commands.focus();
                }}
            >
                <form onSubmit={apply} className="flex gap-1">
                    <Input
                        autoFocus
                        value={url}
                        onChange={e => setUrl(e.target.value)}
                        placeholder="https://"
                        aria-label={t('discussion.format.link_url', 'URL')}
                        className="h-8"
                    />
                    <Button type="submit" size="sm">
                        {url.trim() || !active
                            ? t('discussion.format.link_apply', 'Apply')
                            : t('discussion.format.unlink', 'Remove link')}
                    </Button>
                </form>
            </PopoverContent>
        </Popover>
    );
}
