import {DropzoneOptions, useDropzone} from 'react-dropzone';
import {Box, Typography} from '@mui/material';
import {grey} from '@mui/material/colors';
import React, {ChangeEvent, ChangeEventHandler, FC} from 'react';
import {useTranslation} from 'react-i18next';
import {Control, Controller, FieldPath, FieldValues} from 'react-hook-form';
import FileToUploadCard from '../Upload/FileToUploadCard.tsx';

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
} & DropzoneOptions;

export default function FileDropzoneWidget<TFieldValues extends FieldValues>({
    name,
    control,
    ...rest
}: Props<TFieldValues>) {
    return (
        <Controller
            name={name}
            control={control}
            render={({field: {value, onChange}}) => {
                return (
                    <Dropzone
                        onChange={e => {
                            return onChange(e.target.files?.[0] ?? null);
                        }}
                        value={value}
                        {...rest}
                    />
                );
            }}
        />
    );
}

const Dropzone: FC<{
    value?: File;
    onChange: ChangeEventHandler<HTMLInputElement>;
}> = ({value, onChange, ...rest}) => {
    const {t} = useTranslation();
    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        noClick: true,
        ...rest,
    });

    return (
        <Box
            component={'label'}
            sx={theme => ({
                display: 'block',
                border: `1px dashed ${grey[500]}`,
                borderRadius: theme.shape.borderRadius,
                p: 3,
                mb: 2,
                bgcolor: isDragActive ? 'info.main' : undefined,
                cursor: 'pointer',
            })}
            {...getRootProps()}
        >
            {value ? (
                <>
                    <FileToUploadCard
                        file={value}
                        onRemove={() =>
                            onChange({
                                target: {
                                    value: '',
                                },
                            } as ChangeEvent<HTMLInputElement>)
                        }
                    />
                </>
            ) : (
                <>
                    <input {...getInputProps({onChange})} />
                    <Typography>
                        {t(
                            'upload_dropzone.drag_n_drop_some_files_here_or_click_to_select_files',
                            `Drag 'n' drop some files here, or click to select files`
                        )}
                    </Typography>
                </>
            )}
        </Box>
    );
};
