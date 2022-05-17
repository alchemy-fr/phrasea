import React, {ReactNode} from 'react';
import {Controller} from "react-hook-form";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Control} from "react-hook-form/dist/types/form";
import {FieldPath} from "react-hook-form/dist/types";
import {CollectionsTreeView} from "../Media/Collection/CollectionsTreeView";
import {InputLabel} from "@mui/material";

type Props<TFieldValues extends FieldValues> = {
    label?: ReactNode;
    control: Control<TFieldValues>,
    name: FieldPath<TFieldValues>;
};

export default function CollectionTreeWidget<TFieldValues extends FieldValues>({
                                                                                   name,
                                                                                   control,
                                                                                   label,
                                                                               }: Props<TFieldValues>) {
    return <>
        {label && <InputLabel>
            {label}
        </InputLabel>}
        <Controller
            control={control}
            name={name}
            render={({field: {onChange, value, ref}}) => {
                return <CollectionsTreeView
                    value={value}
                    onChange={(collections) => {
                        onChange(collections);
                    }}
                />
            }}
        />
    </>
}
