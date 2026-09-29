'use client';

import {
    ChangeEvent,
    DragEvent,
    ReactNode,
    Ref,
    useEffect,
    useImperativeHandle,
    useRef,
    useSyncExternalStore,
} from 'react';
import {useTranslation} from 'react-i18next';
import {Editor, JSONContent} from '@tiptap/core';
import {EditorContent, useEditor, useEditorState} from '@tiptap/react';
import {Placeholder} from '@tiptap/extensions';
import {
    AtSignIcon,
    BoldIcon,
    CaseSensitiveIcon,
    CodeIcon,
    ItalicIcon,
    ListIcon,
    ListOrderedIcon,
    LucideIcon,
    PencilIcon,
    PlusIcon,
    SendHorizontalIcon,
    SquareCodeIcon,
    StrikethroughIcon,
    TextQuoteIcon,
    XIcon,
} from 'lucide-react';
import type {User} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';
import {EmojiPicker} from './EmojiPicker';
import {LinkButton} from './LinkButton';
import {useMentionSuggestions} from './MentionSuggestions';
import {PendingAttachmentList} from './MessageAttachments';
import {MessageKeymap, messageExtensions} from './editorExtensions';
import {docToMarkup, markupToDoc, quoteMarkup} from './messageDoc';
import type {FileAttachmentInput} from './messageAttachments';
import {messageContentClass} from './messageStyles';
import {usePendingAttachments} from './usePendingAttachments';

export type MessageComposerHandle = {
    focus: () => void;
    /** Empties the editor and the attachments (after a message is sent) */
    clear: () => void;
    /** Mentions the user at the end of the draft (reply to a message) */
    mention: (user: Pick<User, 'id' | 'username'>) => void;
    /** Quotes a message at the end of the draft, the caret below it */
    quote: (content: string) => void;
};

// The formatting toolbar ("Aa") is shown or hidden per viewer
const toolbarKey = 'discussion.composer.toolbar';
const toolbarListeners = new Set<() => void>();
/** The choice when the storage is unavailable: it lasts for the page */
let toolbarFallback = true;

function readToolbar(): boolean {
    try {
        const stored = localStorage.getItem(toolbarKey);

        return stored === null ? toolbarFallback : stored !== '0';
    } catch {
        return toolbarFallback;
    }
}

function writeToolbar(visible: boolean) {
    toolbarFallback = visible;
    try {
        localStorage.setItem(toolbarKey, visible ? '1' : '0');
    } catch {
        // Storage unavailable (private mode, blocked site data)
    }
    toolbarListeners.forEach(l => l());
}

function subscribeToolbar(listener: () => void) {
    toolbarListeners.add(listener);

    return () => {
        toolbarListeners.delete(listener);
    };
}

function endsWithBlank(editor: Editor): boolean {
    const {doc} = editor.state;
    const text = doc.textBetween(0, doc.content.size, '\n', '\ufffc');

    return text === '' || /\s$/.test(text);
}

function blankBeforeCaret(editor: Editor): boolean {
    const {$from} = editor.state.selection;
    const before = $from.parent.textBetween(
        Math.max(0, $from.parentOffset - 1),
        $from.parentOffset,
        undefined,
        '\ufffc'
    );

    return before === '' || /\s/.test(before);
}

function hasMention(doc: JSONContent, id: string): boolean {
    return (
        (doc.type === 'mention' && doc.attrs?.id === id) ||
        (doc.content ?? []).some(c => hasMention(c, id))
    );
}

/**
 * WYSIWYG message composer (tiptap), Slack-like: formatting toolbar on top,
 * the editor, then attach / toolbar toggle / emoji / mention buttons and the
 * send button. It reads and writes the stored markup (see `messageDoc`),
 * which the legacy client and the API mention extraction also understand.
 *
 * When `editing` is set, the composer holds that message instead of the
 * draft (restored afterwards): Enter saves it, Escape cancels.
 */
export function MessageComposer({
    ref,
    onChange,
    onAttachmentsChange,
    onSubmit,
    editing,
    onSaveEdit,
    onCancelEdit,
    sending,
    placeholder,
}: {
    ref?: Ref<MessageComposerHandle>;
    /** Called with the stored markup on every change */
    onChange?: (content: string) => void;
    /** Called with the number of attached files */
    onAttachmentsChange?: (count: number) => void;
    /** Sends a new message; the caller clears the composer once sent */
    onSubmit: (content: string, attachments: FileAttachmentInput[]) => void;
    editing?: {id: string; content: string} | null;
    onSaveEdit?: (content: string) => void;
    onCancelEdit?: () => void;
    sending?: boolean;
    placeholder?: string;
}) {
    const {t} = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const submitRef = useRef<() => void>(() => {});
    const onChangeRef = useRef(onChange);
    const keyDownRef = useRef<(e: KeyboardEvent) => boolean>(() => false);
    const addFilesRef = useRef<((files: File[]) => boolean) | null>(null);
    const cancelEditRef = useRef<(() => void) | undefined>(undefined);
    const editingRef = useRef(editing);
    /** The draft set aside while a message is edited */
    const draftRef = useRef<JSONContent | null>(null);
    const toolbar = useSyncExternalStore(
        subscribeToolbar,
        readToolbar,
        () => true
    );
    const attachments = usePendingAttachments();

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            ...messageExtensions(),
            Placeholder.configure({placeholder: placeholder ?? ''}),
            MessageKeymap.configure({onSubmit: () => submitRef.current()}),
        ],
        editorProps: {
            attributes: {
                'class': cn(
                    messageContentClass,
                    'max-h-72 min-h-10 overflow-y-auto px-3 py-2 text-sm outline-none',
                    '[&_.is-editor-empty:first-child]:before:pointer-events-none [&_.is-editor-empty:first-child]:before:float-left [&_.is-editor-empty:first-child]:before:h-0 [&_.is-editor-empty:first-child]:before:text-muted-foreground [&_.is-editor-empty:first-child]:before:content-[attr(data-placeholder)]'
                ),
                'role': 'textbox',
                'aria-multiline': 'true',
                ...(placeholder ? {'aria-label': placeholder} : {}),
            },
            // Runs before the keymaps: the mention list gets its keys first
            handleKeyDown: (_view, event) => {
                if (keyDownRef.current(event)) {
                    return true;
                }
                if (event.key === 'Escape') {
                    if (cancelEditRef.current) {
                        cancelEditRef.current();
                    } else {
                        (event.target as HTMLElement).blur();
                    }

                    return true;
                }

                return false;
            },
            handlePaste: (_view, event) =>
                addFilesRef.current?.([
                    ...(event.clipboardData?.files ?? []),
                ]) ?? false,
            handleDrop: (_view, event, _slice, moved) => {
                if (moved) {
                    return false;
                }
                const handled =
                    addFilesRef.current?.([
                        ...(event.dataTransfer?.files ?? []),
                    ]) ?? false;
                if (handled) {
                    event.preventDefault();
                }

                return handled;
            },
        },
        onUpdate: ({editor}) =>
            onChangeRef.current?.(docToMarkup(editor.getJSON())),
    });

    const state = useEditorState({
        editor,
        selector: ({editor}) =>
            editor
                ? {
                      bold: editor.isActive('bold'),
                      italic: editor.isActive('italic'),
                      strike: editor.isActive('strike'),
                      code: editor.isActive('code'),
                      codeBlock: editor.isActive('codeBlock'),
                      bulletList: editor.isActive('bulletList'),
                      orderedList: editor.isActive('orderedList'),
                      blockquote: editor.isActive('blockquote'),
                      empty: !docToMarkup(editor.getJSON()),
                  }
                : null,
    });

    const mentions = useMentionSuggestions(editor);
    const isEditing = !!editing;
    const hasFiles = attachments.items.length > 0;
    const canSend =
        !!state &&
        !sending &&
        (isEditing
            ? !state.empty
            : (!state.empty || hasFiles) &&
              !attachments.uploading &&
              !attachments.failed);

    const submit = () => {
        if (!editor || !canSend) {
            return;
        }
        const content = docToMarkup(editor.getJSON());
        if (isEditing) {
            onSaveEdit?.(content);
        } else {
            onSubmit(content, attachments.inputs);
        }
    };

    const addFiles = (files: File[]): boolean => {
        if (files.length === 0 || editingRef.current) {
            return false;
        }
        attachments.add(files);

        return true;
    };

    useEffect(() => {
        submitRef.current = submit;
        keyDownRef.current = mentions.onKeyDown;
        onChangeRef.current = onChange;
        addFilesRef.current = addFiles;
        editingRef.current = editing;
        cancelEditRef.current = editing ? onCancelEdit : undefined;
    });

    const attachmentCount = attachments.items.length;
    useEffect(() => {
        onAttachmentsChange?.(attachmentCount);
    }, [attachmentCount, onAttachmentsChange]);

    // Entering / leaving the edition of a message
    const editingId = editing?.id;
    useEffect(() => {
        if (!editor) {
            return;
        }
        if (editingId) {
            draftRef.current ??= editor.getJSON();
            editor.commands.setContent(
                markupToDoc(editingRef.current?.content),
                {emitUpdate: false}
            );
            editor.commands.focus('end');
            containerRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        } else if (draftRef.current) {
            editor.commands.setContent(draftRef.current, {emitUpdate: false});
            draftRef.current = null;
        }
    }, [editor, editingId]);

    useImperativeHandle(ref, () => ({
        focus: () => editor?.commands.focus(),
        clear: () => {
            editor?.commands.clearContent(true);
            attachments.clear();
        },
        mention: user => {
            if (!editor) {
                return;
            }
            const chain = editor.chain().focus('end');
            if (!hasMention(editor.getJSON(), user.id)) {
                chain.insertContent([
                    ...(endsWithBlank(editor)
                        ? []
                        : [{type: 'text', text: ' '}]),
                    {
                        type: 'mention',
                        attrs: {id: user.id, username: user.username},
                    },
                    {type: 'text', text: ' '},
                ]);
            }
            chain.run();
            containerRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        },
        quote: content => {
            if (!editor) {
                return;
            }
            const nodes = [
                ...(markupToDoc(quoteMarkup(content)).content ?? []),
                {type: 'paragraph'},
            ];
            if (editor.isEmpty) {
                editor.commands.setContent({type: 'doc', content: nodes});
            } else {
                editor.commands.insertContentAt(
                    editor.state.doc.content.size,
                    nodes
                );
            }
            editor.commands.focus('end');
            containerRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        },
    }));

    type Tool = {
        key: keyof NonNullable<typeof state>;
        icon: LucideIcon;
        label: string;
        run: (e: Editor) => void;
    };
    const tool = (
        key: Tool['key'],
        icon: LucideIcon,
        label: string,
        run: Tool['run']
    ): Tool => ({key, icon, label, run});
    const toolGroups: (Tool | 'link')[][] = [
        [
            tool(
                'bold',
                BoldIcon,
                `${t('discussion.format.bold', 'Bold')} (Ctrl+B)`,
                e => e.chain().focus().toggleBold().run()
            ),
            tool(
                'italic',
                ItalicIcon,
                `${t('discussion.format.italic', 'Italic')} (Ctrl+I)`,
                e => e.chain().focus().toggleItalic().run()
            ),
            tool(
                'strike',
                StrikethroughIcon,
                t('discussion.format.strike', 'Strikethrough'),
                e => e.chain().focus().toggleStrike().run()
            ),
        ],
        [
            'link',
            tool(
                'orderedList',
                ListOrderedIcon,
                t('discussion.format.ordered_list', 'Numbered list'),
                e => e.chain().focus().toggleOrderedList().run()
            ),
            tool(
                'bulletList',
                ListIcon,
                t('discussion.format.bullet_list', 'Bulleted list'),
                e => e.chain().focus().toggleBulletList().run()
            ),
        ],
        [
            tool(
                'blockquote',
                TextQuoteIcon,
                t('discussion.format.quote', 'Quote'),
                e => e.chain().focus().toggleBlockquote().run()
            ),
            tool('code', CodeIcon, t('discussion.format.code', 'Code'), e =>
                e.chain().focus().toggleCode().run()
            ),
            tool(
                'codeBlock',
                SquareCodeIcon,
                t('discussion.format.code_block', 'Code block'),
                e => e.chain().focus().toggleCodeBlock().run()
            ),
        ],
    ];

    const iconButton = (
        label: string,
        icon: ReactNode,
        onClick: () => void,
        props: {
            pressed?: boolean;
            disabled?: boolean;
            className?: string;
        } = {}
    ) => (
        <Tooltip content={label}>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={label}
                aria-pressed={props.pressed}
                disabled={!editor || props.disabled}
                className={cn(
                    props.pressed && 'bg-accent text-accent-foreground',
                    props.className
                )}
                // Keeps the editor focus and selection
                onMouseDown={e => e.preventDefault()}
                onClick={onClick}
            >
                {icon}
            </Button>
        </Tooltip>
    );

    return (
        <div
            ref={containerRef}
            // Files dropped outside of the editor (on the toolbars)
            onDragOver={(e: DragEvent) => {
                if (!isEditing && e.dataTransfer.types.includes('Files')) {
                    e.preventDefault();
                }
            }}
            onDrop={(e: DragEvent) => {
                if (
                    !e.defaultPrevented &&
                    addFiles([...e.dataTransfer.files])
                ) {
                    e.preventDefault();
                }
            }}
            className="overflow-hidden rounded-lg border border-input bg-background shadow-xs transition-[box-shadow] focus-within:ring-2 focus-within:ring-ring/60"
        >
            {isEditing ? (
                <div className="flex items-center gap-2 border-b bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                    <PencilIcon className="size-3.5" />
                    <span className="flex-1">
                        {t('discussion.editing', 'Editing message')}
                    </span>
                    <Tooltip
                        content={`${t('discussion.editing_cancel', 'Cancel editing')} (Esc)`}
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            className="-mr-1.5 text-primary hover:text-primary"
                            aria-label={t(
                                'discussion.editing_cancel',
                                'Cancel editing'
                            )}
                            onClick={onCancelEdit}
                        >
                            <XIcon />
                        </Button>
                    </Tooltip>
                </div>
            ) : null}
            {toolbar ? (
                <div
                    role="toolbar"
                    aria-label={t('discussion.format.toolbar', 'Formatting')}
                    className="flex flex-wrap items-center gap-0.5 border-b bg-muted/50 px-1 py-0.5"
                >
                    {toolGroups.map((group, i) => (
                        <div key={i} className="flex items-center gap-0.5">
                            {i > 0 ? (
                                <div className="mx-1 h-4 w-px bg-border" />
                            ) : null}
                            {group.map(item =>
                                item === 'link' ? (
                                    <LinkButton key="link" editor={editor} />
                                ) : (
                                    <span key={item.key}>
                                        {iconButton(
                                            item.label,
                                            <item.icon />,
                                            () => editor && item.run(editor),
                                            {pressed: !!state?.[item.key]}
                                        )}
                                    </span>
                                )
                            )}
                        </div>
                    ))}
                </div>
            ) : null}
            {editor ? (
                <EditorContent editor={editor} />
            ) : (
                <div className="min-h-10 px-3 py-2 text-sm text-muted-foreground">
                    {placeholder}
                </div>
            )}
            {isEditing ? null : (
                <PendingAttachmentList
                    items={attachments.items}
                    onRemove={attachments.remove}
                />
            )}
            <div className="flex items-center gap-0.5 px-1.5 pb-1.5">
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    hidden
                    onChange={(e: ChangeEvent<HTMLInputElement>) => {
                        addFiles([...(e.target.files ?? [])]);
                        e.target.value = '';
                        editor?.commands.focus();
                    }}
                />
                {iconButton(
                    t('discussion.attach', 'Attach files'),
                    <PlusIcon />,
                    () => fileInputRef.current?.click(),
                    {
                        disabled: isEditing,
                        className:
                            'mr-1 rounded-full bg-muted text-muted-foreground hover:bg-accent',
                    }
                )}
                {iconButton(
                    toolbar
                        ? t('discussion.format.hide', 'Hide formatting')
                        : t('discussion.format.show', 'Show formatting'),
                    <CaseSensitiveIcon />,
                    () => writeToolbar(!toolbar),
                    {pressed: toolbar}
                )}
                <EmojiPicker
                    disabled={!editor}
                    onSelect={emoji =>
                        editor?.chain().focus().insertContent(emoji).run()
                    }
                />
                {iconButton(
                    t('discussion.mention', 'Mention someone'),
                    <AtSignIcon />,
                    () =>
                        editor
                            ?.chain()
                            .focus()
                            .insertContent(
                                blankBeforeCaret(editor) ? '@' : ' @'
                            )
                            .run()
                )}
                <div className="ml-auto">
                    <Tooltip
                        content={
                            isEditing
                                ? t('common.save', 'Save')
                                : attachments.uploading
                                  ? t(
                                        'discussion.attachments_uploading',
                                        'Waiting for the files to be uploaded…'
                                    )
                                  : t('discussion.send', 'Send')
                        }
                    >
                        {/* The tooltip still shows on the disabled button */}
                        <span className="inline-flex">
                            <Button
                                type="button"
                                size="icon-sm"
                                aria-label={
                                    isEditing
                                        ? t('common.save', 'Save')
                                        : t('discussion.send', 'Send')
                                }
                                onClick={submit}
                                disabled={!canSend}
                                loading={sending}
                            >
                                <SendHorizontalIcon />
                            </Button>
                        </span>
                    </Tooltip>
                </div>
            </div>
            {mentions.popup}
        </div>
    );
}
