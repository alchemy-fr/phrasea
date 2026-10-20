'use client';

import {ReactNode, useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {ShapesIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {AnnotationLayer} from './AnnotationLayer';
import {AnnotationToolbar} from './AnnotationToolbar';
import {useAnnotationEditor} from './useAnnotationEditor';
import {useAnnotations, type AnnotationTarget} from './AnnotationsProvider';

type Props = {
    /** Screen pixels per pixel of the media (the player's zoom) */
    zoom: number;
    page?: number;
};

/**
 * The annotation layer of an image player: the shapes of the current target
 * of the {@link AnnotationsProvider} over the image, and the tools to draw
 * new ones when the target is editable.
 *
 * Returns nothing to render without a provider or a target.
 */
export function useAnnotationLayer({zoom, page}: Props): {
    /** To render over the image, in a box following its transform */
    layer: ReactNode;
    toolbar: ReactNode;
    /** The pointer draws or selects shapes instead of panning */
    active: boolean;
    target: AnnotationTarget | undefined;
} {
    const {t} = useTranslation();
    const ctx = useAnnotations();
    const target = ctx?.target;
    const editor = useAnnotationEditor(target, page);

    // The player tells the panel that annotations can be shown
    useEffect(() => {
        if (!ctx) {
            return;
        }
        ctx.setSupported(true);

        return () => ctx.setSupported(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ctx?.setSupported]);

    if (!ctx) {
        return {layer: null, toolbar: null, active: false, target};
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
            active: false,
            target,
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

    return {
        active: editor.active,
        target,
        layer: <AnnotationLayer editor={editor} zoom={zoom} />,
        toolbar: (
            <AnnotationToolbar
                editor={editor}
                label={target.label}
                onClose={() =>
                    isFallback
                        ? ctx.setShowFallback(false)
                        : ctx.clearTarget(target.key)
                }
            />
        ),
    };
}
