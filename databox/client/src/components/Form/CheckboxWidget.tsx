import React, {ReactNode} from 'react';
import {Controller} from "react-hook-form";
import {Checkbox, FormControlLabel} from "@mui/material";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Control} from "react-hook-form/dist/types/form";
import {FieldPath} from "react-hook-form/dist/types";

type Props<TFieldValues extends FieldValues> = {
    label?: ReactNode;
    control: Control<TFieldValues>,
    name: FieldPath<TFieldValues>;
};

export default function CheckboxWidget<TFieldValues extends FieldValues>({
                                                                             name,
                                                                             label,
                                                                             control
                                                                         }: Props<TFieldValues>) {

    return <FormControlLabel
        control={
            <Controller
                name={name}
                control={control}
                render={({field}) => <Checkbox
                    {...field}
                    checked={field.value}
                    onChange={(e) => field.onChange(e.target.checked)}
                />}
            />
        }
        label={label}
        labelPlacement="end"
    />
}
