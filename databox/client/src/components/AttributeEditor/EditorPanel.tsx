import React, {ChangeEvent} from "react";
import {AttributeDefinition} from "../../types.ts";
import {TextField} from "@mui/material";
import {SetAttributeValue, Values} from "./types.ts";

type Props = {
    definition: AttributeDefinition;
    valueContainer: Values;
    setAttributeValue: SetAttributeValue;
};

export default function EditorPanel({
    definition,
    valueContainer,
    setAttributeValue,
}: Props) {
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [value, setValue] = React.useState<string>();

    React.useEffect(() => {
        inputRef.current?.focus();
    }, [definition]);

    React.useEffect(() => {
        setValue(valueContainer.indeterminate ? '' : valueContainer.values[0]);
    }, [definition]);

    const changeHandler = React.useCallback((e: ChangeEvent<HTMLInputElement>) => {
        const v = e.target.value;
        setValue(v);
        setTimeout(() => {
            setAttributeValue(v);
        });
    }, [setAttributeValue]);

    return <>
        <TextField
            inputRef={inputRef}
            autoFocus={true}
            value={value}
            onChange={changeHandler}
            placeholder={valueContainer.indeterminate ? '-------' : undefined}
        />
    </>
}
