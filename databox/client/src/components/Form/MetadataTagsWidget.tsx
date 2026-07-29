import {Autocomplete, Chip, TextField} from '@mui/material';
import {Control, Controller, FieldValues, Path} from 'react-hook-form';

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: Path<TFieldValues>;
    label: string;
    placeholder?: string;
    disabled?: boolean;
};

export default function MetadataTagsWidget<TFieldValues extends FieldValues>({
    control,
    name,
    label,
    placeholder,
    disabled,
}: Props<TFieldValues>) {
    return (
        <Controller
            control={control}
            name={name}
            render={({field: {onChange, value}}) => (
                <Autocomplete<string, true, false, true>
                    multiple
                    freeSolo
                    options={[]}
                    value={(value as string[] | undefined) ?? []}
                    disabled={disabled}
                    onChange={(_e, newValue) => onChange(newValue)}
                    renderTags={(tags, getTagProps) =>
                        tags.map((option, index) => {
                            const {key, ...tagProps} = getTagProps({index});

                            return (
                                <Chip
                                    key={key}
                                    variant="outlined"
                                    label={option}
                                    {...tagProps}
                                />
                            );
                        })
                    }
                    renderInput={params => (
                        <TextField
                            {...params}
                            label={label}
                            placeholder={placeholder}
                        />
                    )}
                />
            )}
        />
    );
}
