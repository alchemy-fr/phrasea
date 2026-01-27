import React, {useCallback, useMemo} from 'react';
import Dropzone from 'react-dropzone';
import filesize from 'filesize';
import {
    Box,
    Button,
    Container,
    Divider,
    Paper,
    Typography,
} from '@mui/material';
import {routes} from '../../routes.ts';
import {toast} from 'react-toastify';
import {StateSetter, Target, UploadedFile} from '../../types.ts';
import {getPath, Link} from '@alchemy/navigation';
import {Trans, useTranslation} from 'react-i18next';
import {config} from '../../init.ts';
import classnames from 'classnames';
import {RemoteErrors} from '@alchemy/react-form';
import FileCard from './FileCard.tsx';
import FileList from './FileList.tsx';

type Props = {
    target: Target;
    files: UploadedFile[];
    setFiles: StateSetter<UploadedFile[]>;
    onSubmit: () => void;
};

enum Classes {
    Dropzone = 'dropzone',
    DropzoneEmpty = 'dropzone-empty',
    DragOver = 'drag-over',
}

export default function FilePicker({target, files, setFiles, onSubmit}: Props) {
    const {t} = useTranslation();
    const allowedTypes = config.allowedTypes;
    const {maxFileSize, maxFileCount, maxCommitSize} = config;

    const totalSize = files.reduce((acc, file) => acc + file.size, 0);

    const errors = useMemo(() => {
        const errors: string[] = [];

        if (maxCommitSize) {
            if (totalSize > maxCommitSize) {
                errors.push(
                    t(
                        'file_picker.total_max_file_size_exceeded',
                        `Total max file size exceeded ({{actualSize}} > {{maxSize}})`,
                        {
                            actualSize: filesize(totalSize),
                            maxSize: filesize(maxCommitSize),
                        }
                    )
                );
            }
        }

        if (maxFileCount) {
            const fileCount = files.length;
            if (fileCount > maxFileCount) {
                errors.push(
                    t(
                        'file_picker.total_max_file_count_exceeded',
                        `Total max file count exceeded ({{fileCount}} > {{maxFileCount}})`,
                        {
                            fileCount,
                            maxFileCount,
                        }
                    )
                );
            }
        }

        return errors;
    }, [files, t]);

    const canSubmit = files.length > 0 && errors.length === 0;

    const removeFile = useCallback(
        (index: number) => {
            setFiles(p => p.filter((_file, i) => i !== index));
        },
        [setFiles]
    );

    const onDrop = React.useCallback(
        (acceptedFiles: File[]) => {
            setFiles(p => {
                const newFiles = acceptedFiles
                    .map((f): UploadedFile | undefined => {
                        if (maxFileSize && f.size > maxFileSize) {
                            toast.error(
                                t(
                                    '',
                                    `Size of {{filename}} is higher than the maximum allowed size of ({{actualSize}} > {{maxSize}})`,
                                    {
                                        filename: f.name,
                                        actualSize: filesize(f.size),
                                        maxSize: filesize(maxFileSize),
                                    }
                                )
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
            <Container
                sx={theme => ({
                    [`.${Classes.Dropzone}`]: {
                        textAlign: 'center',
                        p: 2,
                        border: `2px dashed ${theme.palette.divider}`,
                        borderRadius: theme.shape.borderRadius,
                        minHeight: 186,
                        cursor: 'pointer',
                    },
                    [`.${Classes.DragOver}`]: {
                        backgroundColor: theme.palette.action.hover,
                    },
                    [`.${Classes.DropzoneEmpty}`]: {
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    },
                })}
            >
                <Dropzone
                    onDrop={onDrop}
                    multiple={maxFileCount !== 1}
                    accept={allowedTypes || undefined}
                >
                    {({getRootProps, getInputProps, isDragActive}) => {
                        return (
                            <Paper
                                {...getRootProps()}
                                className={classnames(
                                    {
                                        [Classes.DragOver]: isDragActive,
                                        [Classes.DropzoneEmpty]:
                                            files.length === 0,
                                    },
                                    Classes.Dropzone
                                )}
                            >
                                <input {...getInputProps()} />
                                {files.length > 0 ? (
                                    <FileList>
                                        {files.map((file, index) => {
                                            return (
                                                <FileCard
                                                    key={file.id}
                                                    onRemove={() =>
                                                        removeFile(index)
                                                    }
                                                    file={file}
                                                />
                                            );
                                        })}
                                    </FileList>
                                ) : (
                                    <Typography
                                        variant={'body1'}
                                        sx={{
                                            fontSize: 18,
                                        }}
                                    >
                                        {t(
                                            'file_picker.drag_n_drop_some_files_here_or_click_to_select_files',
                                            `Drag 'n' drop some files here, or click to select files`
                                        )}
                                    </Typography>
                                )}
                            </Paper>
                        );
                    }}
                </Dropzone>

                <Box
                    style={{
                        display: 'flex',
                        flexDirection: 'row',
                    }}
                >
                    <div style={{flexGrow: 1}}></div>
                    <Typography
                        variant={'caption'}
                        sx={{
                            mt: 2,
                            textAlign: 'right',
                        }}
                    >
                        <div>
                            {t('file_picker.file_count', {
                                defaultValue: `You have selected {{count}} file`,
                                defaultValue_other: `You have selected {{count}} files`,
                                count: files.length,
                            })}
                        </div>
                        <div>
                            {maxCommitSize
                                ? t('file_picker.total_size', {
                                      defaultValue: `Total size: {{size}} / {{maxCommitSize}}`,
                                      size: filesize(totalSize),
                                      maxCommitSize: filesize(maxCommitSize),
                                  })
                                : t('file_picker.total_size_no_limit', {
                                      defaultValue: `Total size: {{size}}`,
                                      size: filesize(totalSize),
                                  })}
                        </div>

                        {maxFileSize ? (
                            <div>
                                {t('file_picker.max_file_size', {
                                    defaultValue: `Max file size: {{size}}`,
                                    size: filesize(maxFileSize),
                                })}
                            </div>
                        ) : null}
                    </Typography>
                </Box>

                <RemoteErrors errors={errors} />
                <Box
                    sx={{
                        textAlign: 'center',
                        mt: 2,
                    }}
                >
                    <Button
                        sx={{
                            px: 5,
                        }}
                        size="large"
                        onClick={onSubmit}
                        disabled={!canSubmit}
                        variant={'contained'}
                        color={'primary'}
                    >
                        {t('file_picker.next', `Next`)}
                    </Button>
                </Box>

                <Divider
                    sx={{
                        my: 3,
                    }}
                />
                <Typography variant={'body2'}>
                    <Trans
                        i18nKey={'file_picker.alternative_upload_methods'}
                        defaults={'or just <l>download</l> URLs.'}
                        components={{
                            l: (
                                <Link
                                    to={getPath(routes.download, {
                                        id: target.id,
                                    })}
                                />
                            ),
                        }}
                    />
                </Typography>
            </Container>
        </>
    );
}
