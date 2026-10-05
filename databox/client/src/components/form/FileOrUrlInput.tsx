'use client';

import {useState} from 'react';
import {useDropzone} from 'react-dropzone';
import {useTranslation} from 'react-i18next';
import {LinkIcon, UploadCloudIcon, XIcon} from 'lucide-react';
import {Input} from '@/components/ui/input';
import {Tabs, TabsList, TabsTrigger} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {formatFileSize} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';

export type FileOrUrl = {file?: File; url?: string};

/**
 * Single file picker (drop zone) or remote URL.
 */
export function FileOrUrlInput({
    value,
    onChange,
    accept,
}: {
    value: FileOrUrl;
    onChange: (v: FileOrUrl) => void;
    accept?: Record<string, string[]>;
}) {
    const {t, i18n} = useTranslation();
    const [mode, setMode] = useState<'file' | 'url'>(
        value.url ? 'url' : 'file'
    );
    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        multiple: false,
        accept,
        onDrop: files => files[0] && onChange({file: files[0]}),
    });

    return (
        <div className="space-y-2">
            <Tabs
                value={mode}
                onValueChange={v => setMode(v as 'file' | 'url')}
            >
                <TabsList>
                    <TabsTrigger value="file">
                        <UploadCloudIcon /> {t('form.file.upload', 'Upload')}
                    </TabsTrigger>
                    <TabsTrigger value="url">
                        <LinkIcon /> URL
                    </TabsTrigger>
                </TabsList>
            </Tabs>
            {mode === 'file' ? (
                value.file ? (
                    <div className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                        <span className="min-w-0 flex-1 truncate">
                            {value.file.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {formatFileSize(
                                value.file.size,
                                true,
                                i18n.language
                            )}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={() => onChange({})}
                        >
                            <XIcon />
                        </Button>
                    </div>
                ) : (
                    <div
                        {...getRootProps({
                            className: cn(
                                'flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed px-4 py-6 text-sm text-muted-foreground transition-colors hover:border-primary hover:bg-accent/40',
                                isDragActive && 'border-primary bg-primary/5'
                            ),
                        })}
                    >
                        <input {...getInputProps()} />
                        <UploadCloudIcon className="size-6" />
                        {t(
                            'form.file.drop',
                            'Drop a file here or click to browse'
                        )}
                    </div>
                )
            ) : (
                <Input
                    type="url"
                    placeholder="https://…"
                    value={value.url ?? ''}
                    onChange={e => onChange({url: e.target.value})}
                />
            )}
        </div>
    );
}
