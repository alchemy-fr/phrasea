import {ReactNode} from 'react';
import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import {FormControl, FormLabel} from '@mui/material';
import {RegisterOptions} from 'react-hook-form';
import {IsSelectable} from '../Media/Collection/CollectionTree/collectionTree.ts';
import CollectionsTreeView2, {
    CollectionTreeViewProps2,
} from '../Media/Collection/CollectionTree/CollectionsTreeView2.tsx';

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
    onChange?: CollectionTreeViewProps2<IsMulti>['onChange'];
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
                        <CollectionsTreeView2<IsMulti>
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
