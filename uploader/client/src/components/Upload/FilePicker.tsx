import React from 'react';
import Dropzone from 'react-dropzone';
import filesize from 'filesize';
import {Alert} from '@mui/material';
import {routes} from '../../routes.ts';
import config from '../../config.ts';
import {toast} from 'react-toastify';
import {StateSetter, Target, UploadedFile} from '../../types.ts';
import {getPath, Link} from '@alchemy/navigation';
import AssetUpload from '../AssetUpload';
import {Button} from '@mui/material';
import { useTranslation } from 'react-i18next';

type Props = {
    target: Target;
    files: UploadedFile[];
    setFiles: StateSetter<UploadedFile[]>;
    onSubmit: () => void;
};

export default function FilePicker({target, files, setFiles, onSubmit}: Props) {
    const {t} = useTranslation();
    const allowedTypes = config.allowedTypes;
    const {maxFileSize, maxFileCount, maxCommitSize} = config;

    const totalSize = files.reduce((acc, file) => acc + file.size, 0);

    const errors = React.useMemo(() => {
        const errors: string[] = [];

        if (maxCommitSize) {
            if (totalSize > maxCommitSize) {
                errors.push(
                    `Total max file size exceeded (${filesize(
                        totalSize
                    )} > ${filesize(maxCommitSize)})`
                );
            }
        }

        if (maxFileCount) {
            const fileCount = files.length;
            if (fileCount > maxFileCount) {
                errors.push(
                    `Total max file count exceeded (${fileCount} > ${maxFileCount})`
                );
            }
        }

        return errors;
    }, [files]);

    const canSubmit = files.length > 0 && errors.length === 0;

    const removeFile = React.useCallback((index: number) => {
        setFiles(p => p.filter((_file, i) => i !== index));
    }, []);

    const onDrop = React.useCallback(
        (acceptedFiles: File[]) => {
            setFiles(p => {
                const newFiles = acceptedFiles
                    .map((f): UploadedFile | undefined => {
                        if (maxFileSize && f.size > maxFileSize) {
                            toast.error(
                                `Size of ${f.name} is higher than ${filesize(
                                    maxFileSize
                                )} (${filesize(f.size)})`
                            );

                            return;
                        }

                        (f as UploadedFile).id =
                            '_' + Math.random().toString(36).substr(2, 9);

                        return f as UploadedFile;
                    })
                    .filter(f => !!f) as UploadedFile[];

                if (maxFileCount === 1) {
                    return newFiles;
                }

                return p.concat(newFiles);
            });
        },
        [setFiles]
    );

    return (
        <>
            <div className="upload-container">
                <Dropzone
                    onDrop={onDrop}
                    multiple={maxFileCount !== 1}
                    accept={allowedTypes || undefined}
                >
                    {({getRootProps, getInputProps, isDragActive}) => {
                        const classes = ['Upload'];
                        if (isDragActive) {
                            classes.push('drag-over');
                        }
                        return (
                            <div
                                {...getRootProps()}
                                className={classes.join(' ')}
                            >
                                <input {...getInputProps()} />
                                {files.length > 0 ? (
                                    <div className="file-collection">
                                        {files.map((file, index) => {
                                            return (
                                                <AssetUpload
                                                    key={file.id}
                                                    onRemove={() =>
                                                        removeFile(index)
                                                    }
                                                    file={file}
                                                />
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p>
                                        {t('file_picker.drag_n_drop_some_files_here_or_click_to_select_files', `Drag 'n' drop some files here, or click to select files`)}
                                    </p>
                                )}
                            </div>
                        );
                    }}
                </Dropzone>

                <ul className="specs">
                    <li>
                        {`Files: ${files.length}`}
                        {maxFileCount ? ` / ${maxFileCount}` : ''}
                    </li>
                    <li>
                        {`Total size: ${filesize(totalSize)}`}
                        {maxCommitSize ? ` / ${filesize(maxCommitSize)}` : ''}
                    </li>
                    {maxFileSize ? (
                        <li>{`Max file size: ${filesize(maxFileSize)}`}</li>
                    ) : (
                        ''
                    )}
                </ul>

                {errors.map(err => (
                    <Alert>{err}</Alert>
                ))}
                <Button
                    size="large"
                    onClick={onSubmit}
                    disabled={!canSubmit}
                    variant={'contained'}
                >
                    {t('file_picker.next', `Next`)}
                </Button>

                <hr />
                <p>
                    or just{' '}
                    <Link
                        to={getPath(routes.download, {
                            id: target.id,
                        })}
                    >
                        download
                    </Link>{' '}
                    URLs.
                </p>
            </div>
        </>
    );
}
