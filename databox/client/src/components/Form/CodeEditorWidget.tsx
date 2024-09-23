import {Control, Controller, FieldPath, FieldValues} from 'react-hook-form';
import CodeEditor, {
    CodeEditorProps,
} from '../Media/Asset/Widgets/CodeEditor.tsx';
import {ReactNode} from 'react';
import {FormLabel} from '@mui/material';

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    label?: ReactNode;
    required?: boolean;
    disabled?: boolean;
} & CodeEditorProps;

export default function CodeEditorWidget<TFieldValues extends FieldValues>({
    name,
    control,
    label,
    disabled,
    required,
    ...rest
}: Props<TFieldValues>) {
    return (
        <>
            {label ? (
                <FormLabel
                    required={required}
                    sx={{
                        mb: 1,
                    }}
                >
                    {label}
                </FormLabel>
            ) : (
                ''
            )}
            <Controller
                control={control}
                name={name}
                render={({field: {onChange, value}}) => {
                    return (
                        <CodeEditor
                            {...rest}
                            value={value}
                            readOnly={disabled}
                            onChange={(value, event) => {
                                onChange(value, event);
                                rest.onChange?.(value, event);
                            }}
                        />
                    );
                }}
            />
        </>
    );
}
