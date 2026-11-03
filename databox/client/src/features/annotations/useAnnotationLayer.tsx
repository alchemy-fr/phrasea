'use client';

import {ReactNode, useEffect, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {ShapesIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {AnnotationLayer} from './AnnotationLayer';
import {AnnotationToolbar} from './AnnotationToolbar';
import {
    useAnnotationEditor,
    type AnnotationEditor,
} from './useAnnotationEditor';
import {useAnnotations, type AnnotationTarget} from './AnnotationsProvider';

type Props = {
    /** Screen pixels per pixel of the media (the player's zoom) */
    zoom: number;
    page?: number;
    /**
     * False: nothing is shown nor told to the provider (a player that is
     * not the media of the asset view)
     */
    enabled?: boolean;
};

/**
 * The annotation layer of an image player: the shapes of the current target
 * of the {@link AnnotationsProvider} over the image, and the tools to draw
 * new ones when the target is editable.
 *
 * A document gets one toolbar and one layer per page (`renderLayer()`),
 * sharing the same editor.
 *
 * Returns nothing to render without a provider or a target.
 */
export function useAnnotationLayer({zoom, page, enabled = true}: Props): {
    /** To render over the image, in a box following its transform */
    layer: ReactNode;
    /** The layer of a page of a document, in a box of the size of the page */
    renderLayer: (page: number) => ReactNode;
    toolbar: ReactNode;
    /** The pointer draws or selects shapes instead of panning */
    active: boolean;
    target: AnnotationTarget | undefined;
    /** The target is the shapes of the asset, shown by default */
    isFallback: boolean;
    editor: AnnotationEditor;
} {
    const {t} = useTranslation();
    const providerCtx = useAnnotations();
    const ctx = enabled ? providerCtx : undefined;
    const target = ctx?.target;
    const editor = useAnnotationEditor(target, page);
    const fallback = ctx?.fallback;
    const editing = !!target?.editable && target.key !== fallback?.key;
    const editedValue = target?.attributeId;
    // The other shapes of the asset, kept in sight while some are edited
    const context = useMemo(
        () =>
            editing && fallback
                ? fallback.annotations.filter(
                      a => !editedValue || a.source?.attributeId !== editedValue
                  )
                : undefined,
        [editing, editedValue, fallback]
    );

    // The player tells the panel that annotations can be shown
    useEffect(() => {
        if (!ctx) {
            return;
        }
        ctx.setSupported(true);

        return () => ctx.setSupported(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ctx?.setSupported]);

    const noLayer = () => null;
    if (!ctx) {
        return {
            layer: null,
            renderLayer: noLayer,
            toolbar: null,
            active: false,
            target,
            isFallback: false,
            editor,
        };
    }

    const fallbackCount = ctx.fallback?.annotations.length ?? 0;
    if (!target) {
        // The hidden shapes of the asset can be shown again
        const label = t(
            'annotation.show_asset',
            'Show the annotations of the asset'
        );

        return {
            layer: null,
            renderLayer: noLayer,
            active: false,
            target,
            isFallback: false,
            editor,
            toolbar:
                fallbackCount > 0 ? (
                    <>
                        <Tooltip content={label}>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-xs"
                                aria-label={label}
                                aria-pressed={false}
                                data-tool="show-asset"
                                className="relative"
                                onClick={() => ctx.setShowFallback(true)}
                            >
                                <ShapesIcon />
                                <span className="absolute -top-0.5 -right-0.5 min-w-3.5 rounded-full bg-primary px-0.5 text-center text-[9px] leading-3.5 font-semibold text-primary-foreground">
                                    {fallbackCount}
                                </span>
                            </Button>
                        </Tooltip>
                        <span className="mx-0.5 h-4 w-px bg-border" />
                    </>
                ) : null,
        };
    }

    const isFallback = target.key === ctx.fallback?.key;
    const renderLayer = (layerPage?: number) => (
        <AnnotationLayer
            editor={editor}
            page={layerPage}
            zoom={zoom}
            renderControl={target.editable ? undefined : target.renderControl}
            onEditShape={ctx.editShape}
            canEditShape={ctx.canEditShape}
            context={context}
        />
    );

    return {
        active: editor.active,
        target,
        isFallback,
        editor,
        layer: renderLayer(),
        renderLayer,
        toolbar: (
            <AnnotationToolbar
                editor={editor}
                label={target.label}
                sets={ctx.sets}
                currentSet={isFallback ? (ctx.setId ?? null) : undefined}
                onSelectSet={ctx.showSet}
                onClose={() =>
                    isFallback
                        ? ctx.setShowFallback(false)
                        : ctx.clearTarget(target.key)
                }
            />
        ),
    };
}
