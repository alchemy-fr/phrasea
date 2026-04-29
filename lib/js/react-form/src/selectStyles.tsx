import {Theme} from '@mui/material';
import {StylesConfig} from 'react-select';
import {alpha} from '@mui/material/styles';
import {GroupBase} from 'react-select';
import {SelectOption} from './RSelectWidget';

export function createSelectStyles<
    Option extends SelectOption = SelectOption,
    IsMulti extends boolean = boolean,
    Group extends GroupBase<Option> = GroupBase<Option>,
>(
    theme: Theme,
    error?: boolean,
    styles: StylesConfig<Option, IsMulti, Group> = {},
    inputHeight: number = 56,
    menuWidth?: number
): StylesConfig<Option, IsMulti, Group> {
    const {
        menuPortal,
        control,
        singleValue,
        valueContainer,
        multiValue,
        multiValueLabel,
        multiValueRemove,
        menu,
        option,
        input,
        indicatorsContainer,
        ...stylesRest} = styles;

    return {
        menuPortal: (provided, state) => ({
            ...provided,
            zIndex: theme.zIndex.tooltip + 1,
            width: menuWidth ?? provided.width,
            ...(menuPortal?.(provided, state) ?? {}),
        }),
        control: (provided, state) => ({
            ...provided,
            background: 'transparent',
            borderColor: error
                ? theme.palette.error.main
                : state.isFocused
                  ? theme.palette.primary.main
                  : theme.palette.grey.A400,
            borderRadius: theme.shape.borderRadius,
            minHeight: inputHeight,
            height: !state.isMulti ? inputHeight : undefined,
            ...(control?.(provided, state) ?? {}),
        }),

        singleValue: (provided, state) => ({
            ...provided,
            color: theme.palette.text.primary,
            ...(singleValue?.(provided, state) ?? {}),
        }),

        valueContainer: (provided, state) => ({
            ...provided,
            height: !state.isMulti ? inputHeight : undefined,
            padding: '0 12px',
            color: theme.palette.text.primary,
            ...(valueContainer?.(provided, state) ?? {}),
        }),

        multiValue: (provided, state) => ({
            ...provided,
            fontSize: '1rem',
            backgroundColor: theme.palette.primary.main,
            color: theme.palette.text.primary,
            ...(multiValue?.(provided, state) ?? {}),
        }),

        multiValueLabel: (provided, state) => ({
            ...provided,
            color: theme.palette.primary.contrastText,
            ...(multiValueLabel?.(provided, state) ?? {}),
        }),

        multiValueRemove: (provided, state) => ({
            ...provided,
            color: theme.palette.primary.contrastText,
            ...(multiValueRemove?.(provided, state) ?? {}),
        }),

        menu: (provided, state) => ({
            ...provided,
            backgroundColor: theme.palette.background.paper,
            ...(menu?.(provided, state) ?? {}),
        }),

        option: (provided, state) => ({
            ...provided,
            backgroundColor: state.isDisabled
                ? undefined
                : state.isSelected
                  ? theme.palette.primary.main
                  : state.isFocused
                    ? alpha(theme.palette.primary.main, 0.1)
                    : undefined,
            ...(option?.(provided, state) ?? {}),
        }),
        input: (provided, state) => ({
            ...provided,
            margin: '0px',
            color: theme.palette.text.primary,
            ...(input?.(provided, state) ?? {}),
        }),

        indicatorsContainer: (provided, state) => ({
            ...provided,
            height: inputHeight,
            ...(indicatorsContainer?.(provided, state) ?? {}),
        }),
        ...stylesRest,
    };
}
