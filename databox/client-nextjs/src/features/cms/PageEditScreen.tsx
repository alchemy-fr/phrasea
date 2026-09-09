'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {useQuery} from '@tanstack/react-query';
import {
    EditorContent,
    NodeViewWrapper,
    ReactNodeViewRenderer,
    useEditor,
} from '@tiptap/react';
import type {NodeViewProps} from '@tiptap/react';
import {
    AlignCenterIcon,
    AlignJustifyIcon,
    AlignLeftIcon,
    AlignRightIcon,
    ArrowLeftIcon,
    BoldIcon,
    CodeIcon,
    EraserIcon,
    ExternalLinkIcon,
    HighlighterIcon,
    ItalicIcon,
    LinkIcon,
    ListIcon,
    ListOrderedIcon,
    PaletteIcon,
    PlusIcon,
    QuoteIcon,
    RedoIcon,
    SaveIcon,
    SettingsIcon,
    SquareCodeIcon,
    StrikethroughIcon,
    Trash2Icon,
    UnderlineIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {CmsPage} from '@/types/api';
import {getPage, putPage} from '@/lib/api/misc';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {FullPageLoader} from '@/components/ui/loader';
import {SimpleSelect} from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {PageFormDialog} from './PagesIndexScreen';
import {cmsExtensions, widgetNodes} from './extensions';
import {WidgetRenderer} from './PageRenderer';
import {WidgetOptionsDialog} from './WidgetOptionsDialog';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {useUnsavedChangesPrompt} from '@/hooks/useUnsavedChangesPrompt';

/**
 * TipTap based page editor with formatting toolbar and widget insertion.
 */
export function PageEditScreen({pageId}: {pageId: string}) {
    const page = useQuery({
        queryKey: ['page', pageId],
        queryFn: () => getPage(pageId),
    });

    return (
        <RequireAuth>
            {page.data ? (
                <Editor page={page.data} onSaved={() => page.refetch()} />
            ) : (
                <FullPageLoader />
            )}
        </RequireAuth>
    );
}

function WidgetNodeView(props: NodeViewProps) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    return (
        <NodeViewWrapper
            className={cn(
                'group/widget relative my-4 rounded-lg border border-dashed p-2',
                props.selected && 'ring-2 ring-primary'
            )}
        >
            <div className="absolute top-1 right-1 z-10 flex gap-1 opacity-0 group-hover/widget:opacity-100">
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={() =>
                        openModal(WidgetOptionsDialog, {
                            type: props.node.type.name,
                            attrs: props.node.attrs,
                            onChange: attrs => props.updateAttributes(attrs),
                        })
                    }
                >
                    <SettingsIcon /> {t('cms.widget.options', 'Options')}
                </Button>
                <Button
                    size="sm"
                    variant="secondary"
                    className="text-destructive"
                    onClick={() => props.deleteNode()}
                >
                    <Trash2Icon /> {t('cms.widget.remove', 'Remove widget')}
                </Button>
            </div>
            <div className="pointer-events-none">
                <WidgetRenderer
                    node={{type: props.node.type.name, attrs: props.node.attrs}}
                />
            </div>
        </NodeViewWrapper>
    );
}

function ToolbarButton({
    active,
    onClick,
    label,
    children,
}: {
    active?: boolean;
    onClick: () => void;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <Tooltip content={label}>
            <Button
                variant={active ? 'secondary' : 'ghost'}
                size="icon-sm"
                onClick={onClick}
                aria-label={label}
            >
                {children}
            </Button>
        </Tooltip>
    );
}

const editorExtensions = cmsExtensions.map(ext =>
    widgetNodes.includes(ext as any)
        ? (ext as any).extend({
              addNodeView: () => ReactNodeViewRenderer(WidgetNodeView),
          })
        : ext
);

function Editor({page, onSaved}: {page: CmsPage; onSaved: () => void}) {
    const {t} = useTranslation();
    const router = useRouter();
    const {openModal} = useModals();
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    useUnsavedChangesPrompt(dirty);

    const editor = useEditor({
        extensions: editorExtensions,
        content: (page.data as any) ?? {type: 'doc', content: []},
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose prose-neutral dark:prose-invert max-w-none min-h-[60vh] focus:outline-none px-8 py-6',
            },
        },
        onUpdate: () => setDirty(true),
    });

    useEffect(() => {
        editor?.commands.setContent(
            (page.data as any) ?? {type: 'doc', content: []}
        );
        setDirty(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.id]);

    const save = async () => {
        if (!editor) {
            return;
        }
        setSaving(true);
        try {
            await putPage(page.id, {data: editor.getJSON()});
            setDirty(false);
            onSaved();
            toast.success(t('cms.saved', 'Page saved'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    if (!editor) {
        return <FullPageLoader />;
    }

    const insertWidget = (type: string) =>
        editor.chain().focus().insertContent({type}).run();

    return (
        <div className="flex h-full flex-col">
            <header className="flex h-12 shrink-0 items-center gap-1 border-b px-2">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => router.push(routes.pages())}
                >
                    <ArrowLeftIcon /> {t('cms.pages', 'Pages')}
                </Button>
                <h1 className="min-w-0 flex-1 truncate px-2 text-sm font-semibold">
                    {page.title}
                </h1>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => openModal(PageFormDialog, {page, onSaved})}
                >
                    <SettingsIcon /> {t('cms.edit_meta', 'Page settings')}
                </Button>
                <Button variant="ghost" size="sm" asChild>
                    <a
                        href={page.slug ? routes.page(page.slug) : '/'}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLinkIcon /> {t('common.view', 'View')}
                    </a>
                </Button>
                <Button
                    size="sm"
                    onClick={save}
                    disabled={!dirty}
                    loading={saving}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </header>
            <div className="flex shrink-0 flex-wrap items-center gap-0.5 border-b px-2 py-1">
                <ToolbarButton
                    label={t('common.undo', 'Undo')}
                    onClick={() => editor.chain().focus().undo().run()}
                >
                    <ArrowLeftIcon />
                </ToolbarButton>
                <ToolbarButton
                    label={t('common.redo', 'Redo')}
                    onClick={() => editor.chain().focus().redo().run()}
                >
                    <RedoIcon />
                </ToolbarButton>
                <span className="mx-1 h-5 w-px bg-border" />
                <SimpleSelect
                    size="sm"
                    className="w-36"
                    value={
                        editor.isActive('heading', {level: 1})
                            ? 'h1'
                            : editor.isActive('heading', {level: 2})
                              ? 'h2'
                              : editor.isActive('heading', {level: 3})
                                ? 'h3'
                                : 'p'
                    }
                    onValueChange={v =>
                        v === 'p'
                            ? editor.chain().focus().setParagraph().run()
                            : editor
                                  .chain()
                                  .focus()
                                  .toggleHeading({
                                      level: Number(v.slice(1)) as 1 | 2 | 3,
                                  })
                                  .run()
                    }
                    options={[
                        {value: 'p', label: t('cms.paragraph', 'Paragraph')},
                        {value: 'h1', label: 'Heading 1'},
                        {value: 'h2', label: 'Heading 2'},
                        {value: 'h3', label: 'Heading 3'},
                    ]}
                />
                <SimpleSelect
                    size="sm"
                    className="w-36"
                    value={
                        (editor.getAttributes('textStyle')
                            .fontFamily as string) || 'default'
                    }
                    onValueChange={v =>
                        v === 'default'
                            ? editor.chain().focus().unsetFontFamily().run()
                            : editor.chain().focus().setFontFamily(v).run()
                    }
                    options={[
                        {
                            value: 'default',
                            label: t('cms.font_default', 'Default font'),
                        },
                        {value: 'serif', label: 'Serif'},
                        {value: 'monospace', label: 'Monospace'},
                        {value: 'cursive', label: 'Cursive'},
                    ]}
                />
                <span className="mx-1 h-5 w-px bg-border" />
                <ToolbarButton
                    label="Bold"
                    active={editor.isActive('bold')}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                >
                    <BoldIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Italic"
                    active={editor.isActive('italic')}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                >
                    <ItalicIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Underline"
                    active={editor.isActive('underline')}
                    onClick={() =>
                        editor.chain().focus().toggleUnderline().run()
                    }
                >
                    <UnderlineIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Strike"
                    active={editor.isActive('strike')}
                    onClick={() => editor.chain().focus().toggleStrike().run()}
                >
                    <StrikethroughIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Code"
                    active={editor.isActive('code')}
                    onClick={() => editor.chain().focus().toggleCode().run()}
                >
                    <CodeIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Highlight"
                    active={editor.isActive('highlight')}
                    onClick={() =>
                        editor.chain().focus().toggleHighlight().run()
                    }
                >
                    <HighlighterIcon />
                </ToolbarButton>
                <Tooltip content={t('cms.color', 'Text color')}>
                    <label className="relative inline-flex size-8 cursor-pointer items-center justify-center rounded-md hover:bg-accent">
                        <PaletteIcon className="size-4" />
                        <input
                            type="color"
                            className="absolute inset-0 cursor-pointer opacity-0"
                            onChange={e =>
                                editor
                                    .chain()
                                    .focus()
                                    .setColor(e.target.value)
                                    .run()
                            }
                        />
                    </label>
                </Tooltip>
                <ToolbarButton
                    label={t('cms.clear_format', 'Clear formatting')}
                    onClick={() =>
                        editor
                            .chain()
                            .focus()
                            .unsetAllMarks()
                            .clearNodes()
                            .run()
                    }
                >
                    <EraserIcon />
                </ToolbarButton>
                <span className="mx-1 h-5 w-px bg-border" />
                <ToolbarButton
                    label="Bullet list"
                    active={editor.isActive('bulletList')}
                    onClick={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                >
                    <ListIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Ordered list"
                    active={editor.isActive('orderedList')}
                    onClick={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                >
                    <ListOrderedIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Quote"
                    active={editor.isActive('blockquote')}
                    onClick={() =>
                        editor.chain().focus().toggleBlockquote().run()
                    }
                >
                    <QuoteIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Code block"
                    active={editor.isActive('codeBlock')}
                    onClick={() =>
                        editor.chain().focus().toggleCodeBlock().run()
                    }
                >
                    <SquareCodeIcon />
                </ToolbarButton>
                <ToolbarButton
                    label="Link"
                    active={editor.isActive('link')}
                    onClick={() => {
                        const prev = editor.getAttributes('link').href as
                            | string
                            | undefined;
                        const url = window.prompt(
                            t('cms.link_url', 'Link URL'),
                            prev ?? 'https://'
                        );
                        if (url === null) return;
                        if (url === '') {
                            editor.chain().focus().unsetLink().run();
                        } else {
                            editor
                                .chain()
                                .focus()
                                .extendMarkRange('link')
                                .setLink({href: url})
                                .run();
                        }
                    }}
                >
                    <LinkIcon />
                </ToolbarButton>
                <span className="mx-1 h-5 w-px bg-border" />
                {(['left', 'center', 'right', 'justify'] as const).map(a => (
                    <ToolbarButton
                        key={a}
                        label={`Align ${a}`}
                        active={editor.isActive({textAlign: a})}
                        onClick={() =>
                            editor.chain().focus().setTextAlign(a).run()
                        }
                    >
                        {a === 'left' ? (
                            <AlignLeftIcon />
                        ) : a === 'center' ? (
                            <AlignCenterIcon />
                        ) : a === 'right' ? (
                            <AlignRightIcon />
                        ) : (
                            <AlignJustifyIcon />
                        )}
                    </ToolbarButton>
                ))}
            </div>
            <div className="relative min-h-0 flex-1 overflow-y-auto">
                <div className="mx-auto max-w-5xl">
                    <EditorContent editor={editor} />
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="icon"
                            className="fixed right-6 bottom-6 size-12 rounded-full shadow-lg"
                            aria-label={t('cms.add_widget', 'Add widget')}
                        >
                            <PlusIcon className="size-6" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        side="top"
                        className="w-52"
                    >
                        {[
                            ['assetWidget', t('cms.widget.asset', 'Asset')],
                            [
                                'carouselWidget',
                                t('cms.widget.carousel', 'Carousel'),
                            ],
                            ['gridWidget', t('cms.widget.grid', 'Grid')],
                            [
                                'searchGridWidget',
                                t('cms.widget.search_grid', 'Search grid'),
                            ],
                            ['spacerWidget', t('cms.widget.spacer', 'Spacer')],
                            [
                                'headerBarWidget',
                                t('cms.widget.header', 'Header bar'),
                            ],
                            ['footerWidget', t('cms.widget.footer', 'Footer')],
                        ].map(([type, label]) => (
                            <DropdownMenuItem
                                key={type}
                                onSelect={() => insertWidget(type)}
                            >
                                {label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}
