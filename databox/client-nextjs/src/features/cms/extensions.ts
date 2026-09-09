import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import {TextStyle, Color, FontFamily} from '@tiptap/extension-text-style';
import Image from '@tiptap/extension-image';
import {Node, mergeAttributes} from '@tiptap/react';

/**
 * Atom node used for every page widget. Attributes are kept as JSON; the
 * rendering is done by React (PageRenderer / editor node view).
 */
function widget(name: string, attrs: Record<string, unknown>) {
    return Node.create({
        name,
        group: 'block',
        atom: true,
        draggable: true,
        addAttributes: () =>
            Object.fromEntries(
                Object.entries(attrs).map(([k, v]) => [k, {default: v}])
            ),
        parseHTML: () => [{tag: `div[data-widget="${name}"]`}],
        renderHTML: ({
            HTMLAttributes,
        }: {
            HTMLAttributes: Record<string, unknown>;
        }) => ['div', mergeAttributes(HTMLAttributes, {'data-widget': name})],
    });
}

export const widgetNodes = [
    widget('assetWidget', {assetId: null, width: '100%'}),
    widget('carouselWidget', {assetIds: [], height: 320}),
    widget('gridWidget', {assetIds: [], columns: 4}),
    widget('searchGridWidget', {
        savedSearchId: null,
        maxItems: 20,
        thumbSize: 200,
        height: 600,
        openAsset: true,
        facets: false,
        actions: {basket: false, export: true, share: false},
    }),
    widget('spacerWidget', {height: 40}),
    widget('headerBarWidget', {title: '', background: '', color: ''}),
    widget('footerWidget', {text: ''}),
];

export const cmsExtensions = [
    StarterKit.configure({link: false}),
    Link.configure({openOnClick: false, autolink: true}),
    TextAlign.configure({types: ['heading', 'paragraph']}),
    Highlight.configure({multicolor: true}),
    TextStyle,
    Color,
    FontFamily,
    Image,
    ...widgetNodes,
];
