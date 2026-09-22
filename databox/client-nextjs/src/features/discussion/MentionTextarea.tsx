'use client';

import {useCallback, useEffect, useRef, useState} from 'react';
import {createPortal} from 'react-dom';
import {useQuery} from '@tanstack/react-query';
import {Textarea} from '@/components/ui/input';
import {getUsers} from '@/lib/api/misc';
import {cn} from '@/lib/utils/cn';

/**
 * Textarea with `@user` mentions (server autocomplete). Ctrl+Enter submits,
 * Escape leaves the field. Mentions are silently disabled when the users API
 * is forbidden.
 *
 * The suggestion list is portalled to the body and positioned on the field:
 * the textarea usually sits in a scrolling, clipping container (the asset side
 * panel), where an absolutely positioned list would be cut off.
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
    const [anchor, setAnchor] = useState<{
        left: number;
        width: number;
        /** distance from the viewport edge the list is anchored to */
        top?: number;
        bottom?: number;
    }>();

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

    const place = useCallback(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        const r = el.getBoundingClientRect();
        const below = window.innerHeight - r.bottom;
        // Above the field when there is more room there (the send button and
        // the panel bottom are usually right below)
        setAnchor({
            left: Math.max(4, Math.min(r.left, window.innerWidth - 260)),
            width: Math.max(220, Math.min(r.width, 320)),
            ...(r.top > below
                ? {bottom: window.innerHeight - r.top + 4}
                : {top: r.bottom + 4}),
        });
    }, []);

    const open = suggestions.length > 0;
    useEffect(() => {
        if (!open) {
            return;
        }
        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);

        return () => {
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        };
    }, [open, place]);

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
            {open && anchor
                ? createPortal(
                      <ul
                          data-testid="mention-suggestions"
                          className="fixed z-[70] max-h-60 overflow-y-auto rounded-md border bg-popover p-1 text-sm shadow-md"
                          style={{
                              left: anchor.left,
                              top: anchor.top,
                              bottom: anchor.bottom,
                              width: anchor.width,
                          }}
                      >
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
                      </ul>,
                      document.body
                  )
                : null}
        </div>
    );
}
