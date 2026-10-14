'use client';

import {useTranslation} from 'react-i18next';
import {ShapesIcon} from 'lucide-react';
import type {AssetAnnotation} from '@/types/api';
import {Button, type ButtonProps} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';
import {useAnnotations, useAnnotationTarget} from './AnnotationsProvider';

/**
 * Shows the shapes of something (a value of an attribute…) on the asset, or
 * lets draw them when `editable`. Nothing outside of an asset view.
 */
export function AnnotationTargetButton({
    targetKey,
    annotations,
    editable,
    onChange,
    label,
    disabled,
    size = 'icon-xs',
    className,
}: {
    targetKey: string;
    annotations: AssetAnnotation[];
    editable?: boolean;
    onChange?: (annotations: AssetAnnotation[]) => void;
    /** What is annotated, shown in the player toolbar */
    label?: string;
    disabled?: boolean;
    size?: ButtonProps['size'];
    className?: string;
}) {
    const {t} = useTranslation();
    const ctx = useAnnotations();
    const target = useAnnotationTarget(targetKey, {
        annotations,
        editable,
        onChange,
        label,
    });
    if (!ctx) {
        return null;
    }
    const count = annotations.length;
    const title = !target.supported
        ? t('annotation.unsupported', 'This media cannot be annotated')
        : target.active
          ? t('annotation.hide', 'Hide annotations')
          : editable
            ? t('annotation.edit', 'Annotate on the asset')
            : t('annotation.show', 'Show on the asset');

    return (
        <Tooltip content={title}>
            <span className="inline-flex">
                <Button
                    type="button"
                    variant="ghost"
                    size={size}
                    aria-label={title}
                    aria-pressed={target.active}
                    data-testid="annotation-target-button"
                    disabled={disabled || !target.supported}
                    className={cn(
                        'relative',
                        target.active && 'bg-accent text-accent-foreground',
                        !target.active && count > 0 && 'text-primary',
                        className
                    )}
                    onClick={target.toggle}
                >
                    <ShapesIcon />
                    {count > 0 ? (
                        <span className="absolute -top-1 -right-1 min-w-3.5 rounded-full bg-primary px-0.5 text-center text-[9px] leading-3.5 font-semibold text-primary-foreground">
                            {count}
                        </span>
                    ) : null}
                </Button>
            </span>
        </Tooltip>
    );
}
