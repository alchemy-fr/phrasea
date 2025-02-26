import {Theme} from '@mui/material';
import {StylesConfig} from 'react-select';
import {alpha} from '@mui/material/styles';
import {GroupBase} from 'react-select';

export function createSelectStyles<
    Option = unknown,
    IsMulti extends boolean = boolean,
    Group extends GroupBase<Option> = GroupBase<Option>,
>(
    theme: Theme,
    error?: boolean,
    styles: StylesConfig<Option, IsMulti, Group> = {},
    inputHeight: number = 56,
    menuWidth?: number
): StylesConfig<Option, IsMulti, Group> {
    return {
        menuPortal: base => ({
            ...base,
            zIndex: theme.zIndex.tooltip + 1,
            width: menuWidth ?? base.width,
        }),
        control: (provided, state) => ({
            ...provided,
            background: '#fff',
            borderColor: error
                ? theme.palette.error.main
                : state.isFocused
                  ? theme.palette.primary.main
                  : theme.palette.grey.A400,
            minHeight: inputHeight,
            height: !state.isMulti ? inputHeight : undefined,
        }),

        valueContainer: (provided, state) => ({
            ...provided,
            height: !state.isMulti ? inputHeight : undefined,
            padding: '0 12px',
        }),

        option: (base, {isDisabled, isFocused, isSelected}) => ({
            ...base,
            backgroundColor: isDisabled
                ? undefined
                : isSelected
                  ? theme.palette.primary.main
                  : isFocused
                    ? alpha(theme.palette.primary.main, 0.1)
                    : undefined,
        }),
        input: provided => ({
            ...provided,
            margin: '0px',
        }),

        multiValue: provided => ({
            ...provided,
            fontSize: 20,
        }),

        indicatorsContainer: provided => ({
            ...provided,
            height: inputHeight,
        }),
        ...styles,
    };
}
