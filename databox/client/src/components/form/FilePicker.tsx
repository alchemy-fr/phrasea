'use client';

import {type ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {UploadIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {
    checkUploadFile,
    describeUploadLimits,
    describeUploadRejection,
    type UploadLimits,
} from '@/lib/uploadLimits';

/**
 * A file replacing the current one, or removing it, applied on save:
 * `value` is the file picked, '' to remove the current one, undefined to
 * keep it.
 *
 * With `maxSize`, a help line above the button tells the accepted types and
 * the limit (`help` replaces it), and a file of another type or too large is
 * refused when picked: `onChange` is not called and the reason is shown.
 */
export function FilePicker({
    accept,
    maxSize,
    svgMaxSize,
    help,
    value,
    onChange,
    current,
    uploadLabel,
    removeLabel,
    removedLabel,
    testId,
}: {
    accept: string;
    maxSize?: number;
    /** Limit of an SVG file, instead of `maxSize` */
    svgMaxSize?: number;
    help?: ReactNode;
    value: File | '' | undefined;
    onChange: (v: File | '' | undefined) => void;
    current: ReactNode;
    uploadLabel: string;
    removeLabel: string;
    removedLabel: string;
    testId?: string;
}) {
    const {t, i18n} = useTranslation();
    const [error, setError] = useState<string>();
    const limits: UploadLimits | undefined =
        maxSize !== undefined ? {accept, maxSize, svgMaxSize} : undefined;
    const helpLine =
        help ??
        (limits ? describeUploadLimits(t, limits, i18n.language) : null);

    const change = (v: File | '' | undefined) => {
        setError(undefined);
        onChange(v);
    };

    return (
        <div className="space-y-1.5" data-testid={testId}>
            {helpLine ? (
                <p
                    className="text-xs text-muted-foreground"
                    data-testid={testId ? `${testId}-help` : undefined}
                >
                    {helpLine}
                </p>
            ) : null}
            <div className="flex flex-wrap items-center gap-3">
                {value === undefined ? current : null}
                {value ? (
                    <span className="text-sm">
                        {t('workspace.file.selected', 'New file: {{name}}', {
                            name: value.name,
                        })}
                    </span>
                ) : null}
                {value === '' ? (
                    <span className="text-sm text-destructive">
                        {removedLabel}
                    </span>
                ) : null}
                <Button variant="outline" size="sm" asChild>
                    <label className="cursor-pointer">
                        <UploadIcon /> {uploadLabel}
                        <input
                            type="file"
                            accept={accept}
                            hidden
                            onChange={e => {
                                const file = e.target.files?.[0];
                                e.target.value = '';
                                if (!file) {
                                    setError(undefined);

                                    return;
                                }
                                const rejection = limits
                                    ? checkUploadFile(file, limits)
                                    : null;
                                if (limits && rejection) {
                                    setError(
                                        describeUploadRejection(
                                            t,
                                            file,
                                            rejection,
                                            limits,
                                            i18n.language
                                        )
                                    );

                                    return;
                                }
                                change(file);
                            }}
                        />
                    </label>
                </Button>
                {value !== undefined ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => change(undefined)}
                    >
                        {t('workspace.file.cancel', 'Cancel the change')}
                    </Button>
                ) : current ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive"
                        onClick={() => change('')}
                    >
                        {removeLabel}
                    </Button>
                ) : null}
            </div>
            {error ? (
                <p
                    role="alert"
                    className="text-xs text-destructive"
                    data-testid={testId ? `${testId}-error` : undefined}
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
