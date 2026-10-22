'use client';

import {useTranslation} from 'react-i18next';
import {UploadIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';

/**
 * A file replacing the current one, or removing it, applied on save:
 * `value` is the file picked, '' to remove the current one, undefined to
 * keep it.
 */
export function FilePicker({
    accept,
    value,
    onChange,
    current,
    uploadLabel,
    removeLabel,
    removedLabel,
    testId,
}: {
    accept: string;
    value: File | '' | undefined;
    onChange: (v: File | '' | undefined) => void;
    current: React.ReactNode;
    uploadLabel: string;
    removeLabel: string;
    removedLabel: string;
    testId?: string;
}) {
    const {t} = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-3" data-testid={testId}>
            {value === undefined ? current : null}
            {value ? (
                <span className="text-sm">
                    {t('workspace.file.selected', 'New file: {{name}}', {
                        name: value.name,
                    })}
                </span>
            ) : null}
            {value === '' ? (
                <span className="text-sm text-destructive">{removedLabel}</span>
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
                            if (file) {
                                onChange(file);
                            }
                            e.target.value = '';
                        }}
                    />
                </label>
            </Button>
            {value !== undefined ? (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange(undefined)}
                >
                    {t('workspace.file.cancel', 'Cancel the change')}
                </Button>
            ) : current ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={() => onChange('')}
                >
                    {removeLabel}
                </Button>
            ) : null}
        </div>
    );
}
