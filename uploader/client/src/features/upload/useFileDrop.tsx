'use client';

import {useState} from 'react';
import {
    useDropzone,
    type DropzoneOptions,
    type FileRejection,
} from 'react-dropzone';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {useConfig} from '@/lib/config/ConfigProvider';
import {formatFileSize} from '@/lib/utils/format';
import {
    describeAllowedTypes,
    toDropzoneAccept,
} from '@alchemy/next-ui/utils/mime';
import {mergeSelection, type BatchIssue} from './limits';

/** Names listed in a toast before it only counts the others */
const MAX_LISTED_NAMES = 5;

/**
 * A dropzone adding files to `files` within the limits of the uploader. The
 * refused files are reported grouped by reason, and so are the files that
 * were already selected. `added` is what the drop brought in.
 * `typeRejected` tells that a file was refused for its type since mount.
 */
export function useFileDrop(
    files: File[],
    onSelect: (files: File[], added: File[]) => void,
    options: Pick<
        DropzoneOptions,
        'noDrag' | 'noClick' | 'noKeyboard' | 'disabled'
    > = {}
) {
    const {t} = useTranslation();
    const {upload} = useConfig();
    const single = upload.maxFileCount === 1;
    const [typeRejected, setTypeRejected] = useState(false);

    const listNames = (list: File[]): string => {
        const names = list
            .slice(0, MAX_LISTED_NAMES)
            .map(f => f.name)
            .join(', ');
        const more = list.length - MAX_LISTED_NAMES;

        return more > 0
            ? t('upload.names_and_more', '{{names}} and {{count}} more', {
                  names,
                  count: more,
              })
            : names;
    };

    const reportRejections = (rejections: FileRejection[]) => {
        const tooLarge: File[] = [];
        const wrongType: File[] = [];
        let tooMany = false;
        for (const {file, errors} of rejections) {
            const codes = errors.map(e => e.code);
            if (codes.includes('too-many-files')) {
                tooMany = true;
            } else if (codes.includes('file-too-large')) {
                tooLarge.push(file);
            } else if (codes.includes('file-invalid-type')) {
                wrongType.push(file);
            } else {
                toast.error(`${file.name}: ${errors[0]?.message ?? ''}`);
            }
        }

        if (tooMany) {
            toast.error(
                t(
                    'upload.single_file_only',
                    'Only one file can be sent at a time.'
                )
            );
        }
        if (tooLarge.length > 0) {
            toast.error(
                t('upload.file_too_large', {
                    defaultValue_one:
                        '{{names}} is too large: {{max}} at most per file.',
                    defaultValue_other:
                        '{{count}} files are too large: {{max}} at most per file.',
                    count: tooLarge.length,
                    names: tooLarge[0].name,
                    max: formatFileSize(upload.maxFileSize ?? 0),
                }),
                {
                    description:
                        tooLarge.length > 1 ? listNames(tooLarge) : undefined,
                }
            );
        }
        if (wrongType.length > 0) {
            setTypeRejected(true);
            const types = describeAllowedTypes(upload.allowedTypes);
            toast.error(
                t('upload.file_type_not_allowed', {
                    defaultValue_one: '{{names}} is not an accepted file type.',
                    defaultValue_other:
                        '{{count}} files are not of an accepted type.',
                    count: wrongType.length,
                    names: wrongType[0].name,
                }),
                {
                    description: (
                        <>
                            {wrongType.length > 1 ? (
                                <span className="block">
                                    {listNames(wrongType)}
                                </span>
                            ) : null}
                            {types ? (
                                <span className="block">
                                    {t(
                                        'upload.accepted_types',
                                        'Accepted types: {{types}}',
                                        {types}
                                    )}
                                </span>
                            ) : null}
                        </>
                    ),
                }
            );
        }
    };

    const dropzone = useDropzone({
        accept: toDropzoneAccept(upload.allowedTypes),
        maxSize: upload.maxFileSize,
        multiple: !single,
        ...options,
        onDrop: (accepted, rejections) => {
            reportRejections(rejections);
            if (accepted.length === 0) {
                return;
            }
            const {files: merged, duplicates} = mergeSelection(
                files,
                accepted,
                upload
            );
            if (duplicates > 0) {
                toast.info(
                    t('upload.duplicates', {
                        defaultValue_one:
                            '{{count}} file was already selected: it was left out.',
                        defaultValue_other:
                            '{{count}} files were already selected: they were left out.',
                        count: duplicates,
                    })
                );
            }
            const previous = files[0];
            if (single && previous && previous.name !== merged[0].name) {
                toast.info(
                    t(
                        'upload.file_replaced',
                        '{{name}} replaces {{previous}}.',
                        {
                            name: merged[0].name,
                            previous: previous.name,
                        }
                    )
                );
            }
            const added = single ? merged : merged.slice(files.length);
            if (added.length > 0) {
                onSelect(merged, added);
            }
        },
    });

    return {...dropzone, typeRejected};
}

/** What prevents the batch from being sent, and what to do about it */
export function useBatchIssueMessage(): (issue: BatchIssue) => string {
    const {t} = useTranslation();

    return issue => {
        switch (issue.type) {
            case 'too_many_files':
                return t(
                    'upload.too_many_files',
                    'You selected {{count}} files, at most {{max}} can be sent at once: remove {{excess}}.',
                    {
                        count: issue.count,
                        max: issue.max,
                        excess: issue.count - issue.max,
                    }
                );
            case 'too_large_batch':
                return t(
                    'upload.too_large_batch',
                    'The files weigh {{size}}, at most {{max}} can be sent at once: remove at least {{excess}}.',
                    {
                        size: formatFileSize(issue.size),
                        max: formatFileSize(issue.max),
                        excess: formatFileSize(issue.size - issue.max),
                    }
                );
        }
    };
}
