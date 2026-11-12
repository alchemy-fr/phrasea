'use client';

import {getUserDisplayName} from '@/lib/users';
import {mentionText, rememberUsers} from './mentionUser';
import {useCallback, useEffect, useRef, useState} from 'react';
import {createPortal} from 'react-dom';
import {useQuery} from '@tanstack/react-query';
import type {Editor} from '@tiptap/core';
import type {EditorState} from '@tiptap/pm/state';
import type {User} from '@/types/api';
import {getUsers} from '@/lib/api/misc';
import {cn} from '@alchemy/next-ui/utils/cn';

export type MentionQuery = {from: number; to: number; query: string};

type MentionAnchor = {left: number; top?: number; bottom?: number};

/** Width of the suggestion list, in px (never wider than the screen). */
const POPUP_WIDTH = 320;

/** The `@query` being typed right before the caret, if any. */
export function findMentionQuery(state: EditorState): MentionQuery | null {
    const {selection} = state;
    if (!selection.empty) {
        return null;
    }
    const {$from} = selection;
    if ($from.parent.type.spec.code) {
        return null;
    }
    const before = $from.parent.textBetween(
        Math.max(0, $from.parentOffset - 100),
        $from.parentOffset,
        undefined,
        '\ufffc'
    );
    // Usernames may be e-mail addresses
    const m = /(?:^|\s)@([\w.@+-]*)$/.exec(before);

    return m
        ? {from: $from.pos - m[1].length - 1, to: $from.pos, query: m[1]}
        : null;
}

/**
 * `@user` autocompletion of the composer (server search). The suggestion
 * list is portalled to the body and positioned on the caret: the composer
 * usually sits in a scrolling, clipping container (the asset side panel).
 * Mentions are silently disabled when the users API is forbidden.
 *
 * `onKeyDown` must run before the editor shortcuts (Enter picks the
 * highlighted user instead of sending the message).
 */
export function useMentionSuggestions(editor: Editor | null) {
    const [mention, setMention] = useState<MentionQuery | null>(null);
    const [active, setActive] = useState(0);
    const [anchor, setAnchor] = useState<MentionAnchor>();
    // Escape closes the list until another mention is started
    const dismissed = useRef<number | null>(null);

    const users = useQuery({
        queryKey: ['users', 'mention', mention?.query],
        queryFn: ({signal}) => getUsers({query: mention?.query}, signal),
        enabled: !!mention,
        retry: false,
        staleTime: 30_000,
    });
    const suggestions =
        mention && !users.isError ? (users.data ?? []).slice(0, 8) : [];
    const open = suggestions.length > 0;

    useEffect(() => {
        if (!editor) {
            return;
        }
        const update = () => {
            const q = editor.isFocused ? findMentionQuery(editor.state) : null;
            if (!q || q.from !== dismissed.current) {
                dismissed.current = null;
            }
            const next = q && dismissed.current === null ? q : null;
            setMention(prev =>
                prev?.from === next?.from &&
                prev?.to === next?.to &&
                prev?.query === next?.query
                    ? prev
                    : next
            );
        };
        editor.on('transaction', update);
        editor.on('focus', update);
        editor.on('blur', update);

        return () => {
            editor.off('transaction', update);
            editor.off('focus', update);
            editor.off('blur', update);
        };
    }, [editor]);

    useEffect(() => setActive(0), [mention?.query]);

    const place = useCallback(() => {
        if (!editor || !mention) {
            return;
        }
        const c = editor.view.coordsAtPos(mention.from);
        const below = window.innerHeight - c.bottom;
        setAnchor({
            left: Math.max(
                4,
                Math.min(c.left, window.innerWidth - POPUP_WIDTH - 24)
            ),
            ...(c.top > below
                ? {bottom: window.innerHeight - c.top + 4}
                : {top: c.bottom + 4}),
        });
    }, [editor, mention]);

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

    const pick = (user: User) => {
        if (!editor || !mention) {
            return;
        }
        rememberUsers([user]);
        editor
            .chain()
            .focus()
            .insertContentAt({from: mention.from, to: mention.to}, [
                {
                    type: 'mention',
                    attrs: {id: user.id, username: user.username},
                },
                {type: 'text', text: ' '},
            ])
            .run();
        setMention(null);
    };

    const onKeyDown = (e: KeyboardEvent): boolean => {
        if (!mention) {
            return false;
        }
        if (e.key === 'Escape') {
            dismissed.current = mention.from;
            setMention(null);

            return true;
        }
        if (!open) {
            // Do not send a half-typed mention while the users load
            return e.key === 'Enter' && !e.shiftKey && users.isFetching;
        }
        switch (e.key) {
            case 'ArrowDown':
                setActive(a => (a + 1) % suggestions.length);

                return true;
            case 'ArrowUp':
                setActive(
                    a => (a - 1 + suggestions.length) % suggestions.length
                );

                return true;
            case 'Enter':
            case 'Tab':
                pick(suggestions[Math.min(active, suggestions.length - 1)]);

                return true;
        }

        return false;
    };

    const popup =
        open && anchor
            ? createPortal(
                  <MentionSuggestionList
                      suggestions={suggestions}
                      active={active}
                      anchor={anchor}
                      onPick={pick}
                      onHover={setActive}
                  />,
                  document.body
              )
            : null;

    return {onKeyDown, popup};
}

type MentionSuggestionListProps = {
    suggestions: User[];
    active: number;
    anchor: MentionAnchor;
    onPick: (user: User) => void;
    onHover: (index: number) => void;
};

/**
 * The suggestions: the name, then the `@username` underneath (often an
 * e-mail address), each line cut only when it does not fit, with its full
 * text in `title`.
 */
export function MentionSuggestionList({
    suggestions,
    active,
    anchor,
    onPick,
    onHover,
}: MentionSuggestionListProps) {
    return (
        <ul
            data-testid="mention-suggestions"
            role="listbox"
            className="fixed z-[70] max-h-60 overflow-y-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
            style={{
                left: anchor.left,
                top: anchor.top,
                bottom: anchor.bottom,
                width: POPUP_WIDTH,
                maxWidth: 'calc(100vw - 8px)',
            }}
        >
            {suggestions.map((u, i) => {
                const label = mentionText(u, u.username);

                return (
                    <li
                        key={u.id}
                        role="option"
                        aria-selected={i === active}
                        className={cn(
                            'flex cursor-pointer flex-col rounded-sm px-2 py-1',
                            i === active && 'bg-accent'
                        )}
                        onMouseDown={e => {
                            // Keeps the editor focus
                            e.preventDefault();
                            onPick(u);
                        }}
                        onMouseEnter={() => onHover(i)}
                    >
                        <span className="truncate" title={label}>
                            {label}
                        </span>
                        {getUserDisplayName(u) !== u.username ? (
                            <span
                                className="truncate text-xs text-muted-foreground"
                                title={'@' + u.username}
                            >
                                @{u.username}
                            </span>
                        ) : null}
                    </li>
                );
            })}
        </ul>
    );
}
