import React, {ChangeEvent} from "react";
import {Asset, AttributeDefinition} from "../../types.ts";
import {Box, TextField} from "@mui/material";
import {SetAttributeValue, Values} from "./types.ts";

type Props = {
    definition: AttributeDefinition;
    valueContainer: Values;
    subSelection: Asset[];
    setAttributeValue: SetAttributeValue;
    inputValueInc: number;
};

export default function EditorPanel({
    definition,
    valueContainer,
    setAttributeValue,
    subSelection,
    inputValueInc,
}: Props) {
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [value, setValue] = React.useState<string>('');

    React.useEffect(() => {
        inputRef.current?.focus();
    }, [definition, valueContainer, inputValueInc]);

    React.useEffect(() => {
        setValue(valueContainer.indeterminate ? '' : (valueContainer.values[0] ?? ''));
    }, [definition, subSelection, inputValueInc]);

    const changeHandler = React.useCallback((e: ChangeEvent<HTMLInputElement>) => {
        const v = e.target.value;
        setValue(v);
        setTimeout(() => {
            setAttributeValue(v);
        });
    }, [setAttributeValue]);

    return <Box
        sx={{
            p: 1,
        }}
    >
        <TextField
            style={{
                width: '100%',
            }}
            inputRef={inputRef}
            autoFocus={true}
            value={value}
            onChange={changeHandler}
            placeholder={valueContainer.indeterminate ? '-------' : undefined}
        />
    </Box>
}
