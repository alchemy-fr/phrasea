import React, {ReactNode} from 'react';
import {Controller} from "react-hook-form";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Control} from "react-hook-form/dist/types/form";
import {FieldPath} from "react-hook-form/dist/types";
import {CollectionsTreeView} from "../Media/Collection/CollectionsTreeView";
import {InputLabel} from "@mui/material";
import {RegisterOptions} from "react-hook-form/dist/types/validator";

type Props<TFieldValues extends FieldValues> = {
    label?: ReactNode;
    control: Control<TFieldValues>,
    name: FieldPath<TFieldValues>;
    multiple?: boolean;
    rules?: Omit<RegisterOptions<TFieldValues, FieldPath<TFieldValues>>, 'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'>;
};

export default function CollectionTreeWidget<TFieldValues extends FieldValues>({
                                                                                   name,
                                                                                   control,
                                                                                   rules,
                                                                                   label,
                                                                                   multiple,
                                                                               }: Props<TFieldValues>) {
    return <>
        {label && <InputLabel>
            {label}
        </InputLabel>}
        <Controller
            control={control}
            name={name}
            rules={rules}
            render={({field: {onChange, value, ref}}) => {
                return <CollectionsTreeView
                    value={value}
                    multiple={multiple}
                    onChange={(collections) => {
                        onChange(collections);
                    }}
                />
            }}
        />
    </>
}
