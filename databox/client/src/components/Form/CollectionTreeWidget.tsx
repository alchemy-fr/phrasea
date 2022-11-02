import React, {ReactNode} from 'react';
import {Controller} from "react-hook-form";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Control} from "react-hook-form/dist/types/form";
import {FieldPath} from "react-hook-form/dist/types";
import {CollectionsTreeView} from "../Media/Collection/CollectionsTreeView";
import {FormControl, FormLabel} from "@mui/material";
import {RegisterOptions} from "react-hook-form/dist/types/validator";

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    label?: ReactNode;
    control: Control<TFieldValues>,
    required?: boolean | undefined;
    name: FieldPath<TFieldValues>;
    multiple?: IsMulti;
    rules?: Omit<RegisterOptions<TFieldValues, FieldPath<TFieldValues>>, 'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'>;
    onChange?: (selection: IsMulti extends true ? string[] : string, workspaceId?: IsMulti extends true ? string : never) => void;
    workspaceId?: string;
    allowNew?: boolean | undefined;
};

export default function CollectionTreeWidget<TFieldValues extends FieldValues,
    IsMulti extends boolean = false>({
                                         name,
                                         control,
                                         rules,
                                         label,
                                         multiple,
                                         onChange: extOnChange,
                                         workspaceId,
                                         required,
                                         allowNew,
                                     }: Props<TFieldValues, IsMulti>) {
    return <FormControl component="fieldset" variant="standard">
        {label && <FormLabel
            required={required}
            component="legend"
            sx={{
                mb: 1
            }}
        >
            {label}
        </FormLabel>}
        <Controller
            control={control}
            name={name}
            rules={rules}
            render={({field: {onChange, value, ref}}) => {
                return <CollectionsTreeView
                    workspaceId={workspaceId}
                    value={value}
                    multiple={multiple}
                    allowNew={allowNew}
                    onChange={(collections, ws) => {
                        onChange(collections);
                        extOnChange && extOnChange(collections, ws);
                    }}
                />
            }}
        />
    </FormControl>
}
