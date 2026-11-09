'use client';

import {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * The submit button of an editor, kept at the bottom of the page while it
 * scrolls; it only saves once something changed.
 */
export function SaveBar({
    dirty,
    saving,
    disabled,
    children,
    className,
}: {
    dirty: boolean;
    saving: boolean;
    /** The changes cannot be saved as they are */
    disabled?: boolean;
    /** Other actions, beside the button */
    children?: ReactNode;
    className?: string;
}) {
    const {t} = useTranslation();

    return (
        <div
            data-testid="save-bar"
            className={cn(
                'sticky bottom-0 z-10 flex flex-wrap items-center gap-3 border-t bg-background/95 py-3 backdrop-blur',
                className
            )}
        >
            <Button
                type="submit"
                loading={saving}
                disabled={disabled || !dirty}
                data-testid="form-schema-save"
            >
                <SaveIcon /> {t('admin.save', 'Save Form')}
            </Button>
            {children}
            {dirty && !saving ? (
                <span className="text-sm text-muted-foreground">
                    {t('admin.unsaved_changes', 'Unsaved changes')}
                </span>
            ) : null}
        </div>
    );
}
