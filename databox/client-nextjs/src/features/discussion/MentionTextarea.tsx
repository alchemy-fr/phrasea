'use client';

import {useEffect, useRef, useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {Textarea} from '@/components/ui/input';
import {getUsers} from '@/lib/api/misc';
import {cn} from '@/lib/utils/cn';

/**
 * Textarea with `@user` mentions (server autocomplete). Ctrl+Enter submits,
 * Escape leaves the field. Mentions are silently disabled when the users API
 * is forbidden.
 */
export function MentionTextarea({
    value,
    onChange,
    placeholder,
    onSubmit,
}: {
    value: string;
    onChange: (v: string) => void;
    placeholder?: string;
    onSubmit?: () => void;
}) {
    const ref = useRef<HTMLTextAreaElement>(null);
    const [mention, setMention] = useState<{
        start: number;
        query: string;
    } | null>(null);
    const [active, setActive] = useState(0);

    const users = useQuery({
        queryKey: ['users', 'mention', mention?.query],
        queryFn: ({signal}) => getUsers(mention?.query || undefined, signal),
        enabled: !!mention,
        retry: false,
        staleTime: 30_000,
    });
    const suggestions =
        mention && !users.isError ? (users.data ?? []).slice(0, 8) : [];

    useEffect(() => setActive(0), [mention?.query]);

    const detectMention = (text: string, caret: number) => {
        const before = text.slice(0, caret);
        const m = before.match(/(?:^|\s)@([\w.-]*)$/);
        setMention(m ? {start: caret - m[1].length - 1, query: m[1]} : null);
    };

    const pick = (username: string) => {
        if (!mention) {
            return;
        }
        const el = ref.current!;
        const caret = el.selectionStart;
        const next = `${value.slice(0, mention.start)}@${username} ${value.slice(caret)}`;
        onChange(next);
        setMention(null);
        requestAnimationFrame(() => {
            const pos = mention.start + username.length + 2;
            el.setSelectionRange(pos, pos);
            el.focus();
        });
    };

    return (
        <div className="relative">
            <Textarea
                ref={ref}
                value={value}
                placeholder={placeholder}
                className="min-h-20 text-sm"
                onChange={e => {
                    onChange(e.target.value);
                    detectMention(e.target.value, e.target.selectionStart);
                }}
                onKeyDown={e => {
                    if (suggestions.length > 0) {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            setActive(a =>
                                Math.min(suggestions.length - 1, a + 1)
                            );

                            return;
                        }
                        if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            setActive(a => Math.max(0, a - 1));

                            return;
                        }
                        if (e.key === 'Enter' || e.key === 'Tab') {
                            e.preventDefault();
                            pick(suggestions[active].username);

                            return;
                        }
                    }
                    if (e.key === 'Escape') {
                        if (mention) {
                            setMention(null);
                        } else {
                            (e.target as HTMLTextAreaElement).blur();
                        }
                    }
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        onSubmit?.();
                    }
                }}
            />
            {suggestions.length > 0 ? (
                <ul className="absolute bottom-full left-0 z-20 mb-1 w-64 rounded-md border bg-popover p-1 text-sm shadow-md">
                    {suggestions.map((u, i) => (
                        <li
                            key={u.id}
                            className={cn(
                                'cursor-pointer rounded-sm px-2 py-1',
                                i === active && 'bg-accent'
                            )}
                            onMouseDown={e => {
                                e.preventDefault();
                                pick(u.username);
                            }}
                            onMouseEnter={() => setActive(i)}
                        >
                            @{u.username}
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
