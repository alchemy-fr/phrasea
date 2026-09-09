'use client';

import {useMemo} from 'react';
import {generateHTML} from '@tiptap/react';
import type {CmsPage} from '@/types/api';
import {cmsExtensions} from './extensions';
import {SearchGridWidget} from './widgets/SearchGridWidget';
import {AssetWidget} from './widgets/AssetWidget';

type Node = {type: string; attrs?: Record<string, any>; content?: Node[]};

/**
 * Renders a CMS page: TipTap JSON to HTML, with widget nodes (asset, search
 * grid, spacer, header / footer bars) rendered as React components.
 */
export function PageRenderer({page}: {page: CmsPage}) {
    const blocks = useMemo(
        () => splitBlocks((page.data ?? {type: 'doc', content: []}) as Node),
        [page.data]
    );

    return (
        <article className="mx-auto w-full max-w-5xl space-y-6 overflow-y-auto p-6">
            {blocks.map((block, i) =>
                block.widget ? (
                    <WidgetRenderer key={i} node={block.widget} />
                ) : (
                    <div
                        key={i}
                        className="prose prose-neutral max-w-none dark:prose-invert"
                        dangerouslySetInnerHTML={{
                            __html: generateHTML(block.doc!, cmsExtensions),
                        }}
                    />
                )
            )}
        </article>
    );
}

export const widgetTypes = [
    'assetWidget',
    'carouselWidget',
    'gridWidget',
    'searchGridWidget',
    'spacerWidget',
    'headerBarWidget',
    'footerWidget',
];

function splitBlocks(doc: Node): {doc?: Node; widget?: Node}[] {
    const out: {doc?: Node; widget?: Node}[] = [];
    let current: Node[] = [];
    const flush = () => {
        if (current.length > 0) {
            out.push({doc: {type: 'doc', content: current}});
            current = [];
        }
    };
    (doc.content ?? []).forEach(node => {
        if (widgetTypes.includes(node.type)) {
            flush();
            out.push({widget: node});
        } else {
            current.push(node);
        }
    });
    flush();

    return out;
}

export function WidgetRenderer({node}: {node: Node}) {
    const attrs = node.attrs ?? {};
    switch (node.type) {
        case 'spacerWidget':
            return <div style={{height: attrs.height ?? 40}} />;
        case 'headerBarWidget':
            return (
                <header
                    className="rounded-lg px-6 py-4 text-2xl font-bold"
                    style={{
                        backgroundColor: attrs.background ?? 'transparent',
                        color: attrs.color ?? undefined,
                    }}
                >
                    {attrs.title}
                </header>
            );
        case 'footerWidget':
            return (
                <footer className="border-t pt-4 text-center text-sm text-muted-foreground">
                    {attrs.text}
                </footer>
            );
        case 'assetWidget':
            return <AssetWidget assetId={attrs.assetId} />;
        case 'carouselWidget':
        case 'gridWidget':
            return (
                <AssetWidget
                    assetId={attrs.assetIds?.[0]}
                    ids={attrs.assetIds}
                    layout={
                        node.type === 'carouselWidget' ? 'carousel' : 'grid'
                    }
                />
            );
        case 'searchGridWidget':
            return <SearchGridWidget attrs={attrs} />;
        default:
            return null;
    }
}
