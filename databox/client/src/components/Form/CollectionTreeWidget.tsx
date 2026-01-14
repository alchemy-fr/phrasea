import {ReactNode} from 'react';
import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import {FormControl, FormLabel} from '@mui/material';
import {RegisterOptions} from 'react-hook-form';
import CollectionsTreeView, {
    CollectionTreeViewProps,
} from '../Media/Collection/CollectionTree/CollectionsTreeView.tsx';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    label?: ReactNode;
    control: Control<TFieldValues>;
    required?: boolean | undefined;
    name: FieldPath<TFieldValues>;
    multiple?: IsMulti;
    rules?: Omit<
        RegisterOptions<TFieldValues, FieldPath<TFieldValues>>,
        'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
    >;
    workspaceId?: string;
    allowNew?: boolean | undefined;
} & CollectionTreeViewProps<IsMulti>;

export default function CollectionTreeWidget<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false,
>({
    name,
    control,
    rules,
    label,
    multiple,
    onChange: extOnChange,
    workspaceId,
    required,
    allowNew,
    ...widgetProps
}: Props<TFieldValues, IsMulti>) {
    return (
        <FormControl fullWidth>
            {label && (
                <FormLabel
                    required={required}
                    component="legend"
                    sx={{
                        mb: 1,
                    }}
                >
                    {label}
                </FormLabel>
            )}
            <Controller
                control={control}
                name={name}
                rules={rules}
                render={({field: {onChange}}) => {
                    return (
                        <CollectionsTreeView<IsMulti>
                            {...widgetProps}
                            required={required}
                            workspaceId={workspaceId}
                            multiple={multiple}
                            allowNew={allowNew}
                            onChange={collections => {
                                onChange(collections);
                                extOnChange && extOnChange(collections);
                            }}
                        />
                    );
                }}
            />
        </FormControl>
    );
}
