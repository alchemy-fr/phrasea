import React from 'react';
import {Box, Button, FormLabel, IconButton, TextField} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import {Control, Controller, FieldValues, Path} from 'react-hook-form';
import {useTranslation} from 'react-i18next';

type MetadataValues = Record<string, string>;

type Row = {id: number; key: string; value: string};

function toRecord(rows: Row[]): MetadataValues {
    const record: MetadataValues = {};
    for (const row of rows) {
        const key = row.key.trim();
        if (key) {
            record[key] = row.value;
        }
    }

    return record;
}

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: Path<TFieldValues>;
    label: string;
    disabled?: boolean;
};

export default function MetadataValuesWidget<TFieldValues extends FieldValues>({
    control,
    name,
    label,
    disabled,
}: Props<TFieldValues>) {
    return (
        <Controller
            control={control}
            name={name}
            render={({field: {onChange, value}}) => (
                <MetadataValuesEditor
                    value={(value as MetadataValues | undefined | null) ?? {}}
                    onChange={onChange}
                    label={label}
                    disabled={disabled}
                />
            )}
        />
    );
}

type EditorProps = {
    value: MetadataValues;
    onChange: (value: MetadataValues) => void;
    label: string;
    disabled?: boolean;
};

function MetadataValuesEditor({value, onChange, label, disabled}: EditorProps) {
    const {t} = useTranslation();
    const nextId = React.useRef(0);
    const createRows = (record: MetadataValues): Row[] =>
        Object.entries(record).map(([key, v]) => ({
            id: nextId.current++,
            key,
            value: v,
        }));

    const [rows, setRows] = React.useState<Row[]>(() => createRows(value));

    React.useEffect(() => {
        // Resync rows when the form value is changed externally (e.g. form reset)
        setRows(currentRows => {
            if (
                JSON.stringify(toRecord(currentRows)) !== JSON.stringify(value)
            ) {
                return createRows(value);
            }

            return currentRows;
        });
    }, [value]);

    const updateRows = (newRows: Row[]) => {
        setRows(newRows);
        onChange(toRecord(newRows));
    };

    return (
        <>
            <FormLabel>{label}</FormLabel>
            {rows.map((row, index) => (
                <Box
                    key={row.id}
                    sx={{
                        display: 'flex',
                        gap: 1,
                        alignItems: 'center',
                        mt: 1,
                    }}
                >
                    <TextField
                        label={t(
                            'form.metadata_values.tag.label',
                            'Tag (e.g. IPTC:CopyrightNotice)'
                        )}
                        value={row.key}
                        disabled={disabled}
                        onChange={e =>
                            updateRows(
                                rows.map((r, i) =>
                                    i === index
                                        ? {...r, key: e.target.value}
                                        : r
                                )
                            )
                        }
                        sx={{flexGrow: 1}}
                    />
                    <TextField
                        label={t('form.metadata_values.value.label', 'Value')}
                        value={row.value}
                        disabled={disabled}
                        onChange={e =>
                            updateRows(
                                rows.map((r, i) =>
                                    i === index
                                        ? {...r, value: e.target.value}
                                        : r
                                )
                            )
                        }
                        sx={{flexGrow: 1}}
                    />
                    <IconButton
                        disabled={disabled}
                        onClick={() =>
                            updateRows(rows.filter((_r, i) => i !== index))
                        }
                        aria-label={t('form.metadata_values.remove', 'Remove')}
                    >
                        <DeleteIcon />
                    </IconButton>
                </Box>
            ))}
            <Box sx={{mt: 1}}>
                <Button
                    startIcon={<AddIcon />}
                    disabled={disabled}
                    onClick={() =>
                        setRows([
                            ...rows,
                            {id: nextId.current++, key: '', value: ''},
                        ])
                    }
                >
                    {t('form.metadata_values.add', 'Add metadata')}
                </Button>
            </Box>
        </>
    );
}
