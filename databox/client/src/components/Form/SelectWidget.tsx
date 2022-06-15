import {Box, FormControl, InputLabel, MenuItem, Select, SelectProps, SvgIcon, Theme} from "@mui/material";
import React, {ReactNode} from "react";
import {Controller} from "react-hook-form";
import {Control} from "react-hook-form/dist/types/form";
import {SxProps} from "@mui/system";

type Option = {
    label: ReactNode;
    value: string;
    image?: React.ElementType | React.FC | string;
};

export type {Option as SelectOption};

type Props = {
    options: Option[];
    label?: ReactNode;
    control?: Control<any>;
    disabledValues?: string[];
} & SelectProps<string>;

export type {Props as SelectWidgetProps};

const sx1 = (theme: Theme) => ({
    marginRight: theme.spacing(1),
    display: 'inline-block',
    verticalAlign: 'middle',
});

export type TSelect = ((props: SelectProps<string>) => JSX.Element) & {
    muiName: string;
};

const defaultSx: SxProps<Theme> = {
    m: 1,
    minWidth: 160,
    margin: 0,
};

const Widget = React.forwardRef<TSelect, Props>(({
                                                     id = 'id',
                                                     name,
                                                     control,
                                                     defaultValue,
                                                     options,
                                                     label,
                                                     sx,
                                                     value,
                                                     ...props
                                                 }, ref) => {
    return <Select<string>
        label={label}
        inputRef={ref}
        autoWidth
        labelId={`${id}-label`}
        id={id!}
        defaultValue={defaultValue || ''}
        {...props}
    >
        {options.map(({value, label, image}) => {
            const img = image && (typeof image === 'string' ? <Box
                sx={sx1}
            >
                <img
                    src={image}
                    alt={value}
                />
            </Box> : <Box
                sx={sx1}
            >
                <SvgIcon component={image} inheritViewBox/>
            </Box>);

            return <MenuItem
                key={value}
                value={value}>
                {img}
                {label}
            </MenuItem>
        })}
    </Select>
});

const SelectWidget = React.forwardRef<TSelect, Props>(({
                                                           id = 'id',
                                                           name,
                                                           control,
                                                           defaultValue,
                                                           multiple,
                                                           options,
                                                           required,
                                                           label,
                                                           sx,
                                                           value,
                                                           ...props
                                                       }, ref) => {
    return <FormControl sx={sx ? {
        ...defaultSx,
        ...sx,
    } : defaultSx}>
        <InputLabel
            id={`${id}-label`}
            required={required}
        >{label}</InputLabel>
        {control && <Controller
            render={({field}) => {
                return <Widget
                    id={id}
                    {...props}
                    {...field}
                    label={label}
                    options={options}
                    ref={field.ref}
                />
            }}
            name={name!}
            control={control}
            defaultValue={defaultValue || ''}
        />}
        {!control && <Widget
            id={id}
            {...props}
            label={label}
            options={options}
            ref={ref}
        />}
    </FormControl>
});

export default SelectWidget;
