import {ReactNode} from 'react';
import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import {
    CollectionsTreeView,
    CollectionTreeViewProps,
    IsSelectable,
} from '../Media/Collection/CollectionsTreeView';
import {FormControl, FormLabel} from '@mui/material';
import {RegisterOptions} from 'react-hook-form';

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
    onChange?: CollectionTreeViewProps<IsMulti>['onChange'];
    workspaceId?: string;
    allowNew?: boolean | undefined;
    disabled?: boolean | undefined;
    isSelectable?: IsSelectable;
};

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
    isSelectable,
    allowNew,
    disabled,
}: Props<TFieldValues, IsMulti>) {
    return (
        <FormControl component="fieldset" variant="standard">
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
                render={({field: {onChange, value}}) => {
                    return (
                        <CollectionsTreeView<IsMulti>
                            workspaceId={workspaceId}
                            disabled={disabled}
                            value={value}
                            multiple={multiple}
                            allowNew={allowNew}
                            isSelectable={isSelectable}
                            onChange={(collections, ws) => {
                                onChange(collections);
                                extOnChange && extOnChange(collections, ws);
                            }}
                        />
                    );
                }}
            />
        </FormControl>
    );
}
