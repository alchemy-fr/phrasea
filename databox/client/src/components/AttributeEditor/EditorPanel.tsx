import React from "react";
import {Asset, AttributeDefinition, StateSetter} from "../../types.ts";
import {Alert, Box, Tab, Tabs} from "@mui/material";
import {SetAttributeValue, Values} from "./types.ts";
import AttributeWidget from "./AttributeWidget.tsx";
import Flag from "../Ui/Flag.tsx";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";
import MultiAttributeRow from "./MultiAttributeRow.tsx";

type Props<T> = {
    definition: AttributeDefinition;
    valueContainer: Values;
    subSelection: Asset[];
    setAttributeValue: SetAttributeValue<T>;
    inputValueInc: number;
    currentLocale: string;
    setCurrentLocale: StateSetter<string>;
};

export default function EditorPanel<T>({
    definition,
    valueContainer,
    setAttributeValue,
    subSelection,
    inputValueInc,
    currentLocale,
    setCurrentLocale,
}: Props<T>) {
    const disabled = false; // TODO
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [value, setValue] = React.useState<T>();

    React.useEffect(() => {
        inputRef.current?.focus();
    }, [definition, valueContainer, inputValueInc, currentLocale]);

    React.useEffect(() => {
        setValue(valueContainer.indeterminate ? '' : (valueContainer.values[0] ?? ''));
    }, [definition, subSelection, inputValueInc, currentLocale]);

    const changeHandler = React.useCallback((v: any) => {
        setValue(v);
        setTimeout(() => {
            setAttributeValue(v);
        });
    }, [setAttributeValue]);

    if (definition.translatable && definition.locales!.length === 0) {
        return (
            <Alert severity={'warning'}>
                No locale defined in this workspace
            </Alert>
        );
    }


    const humanLocale = (l: string) => (l === NO_LOCALE ? `Untranslated` : l);

    const readOnly = !definition.canEdit;

    return <Box
        sx={{
            p: 1,
        }}
    >
        {definition.translatable && definition.locales ? <>
            <Tabs
                value={currentLocale}
                onChange={(_e, value) => setCurrentLocale(value)}
                aria-label="Locales"
                sx={{
                    '.MuiTab-root': {
                        textTransform: 'none',
                    },
                }}
            >
                {definition.locales.map(l => (
                    <Tab
                        key={l}
                        label={
                            <>
                                <Flag locale={l} sx={{mb: 1}} />
                                {humanLocale(l)}
                            </>
                        }
                        value={l}
                    />
                ))}
            </Tabs>
        </> : ''}

        {definition.multiple ? (
            <MultiAttributeRow
                indeterminate={valueContainer.indeterminate}
                readOnly={readOnly}
                isRtl={false}
                disabled={disabled}
                type={definition.fieldType}
                name={definition.name}
                values={value as unknown as (T[] | undefined)}
                onChange={changeHandler}
                id={definition.id}
            />
        ) : (
            <AttributeWidget<T>
                indeterminate={valueContainer.indeterminate}
                readOnly={readOnly}
                isRtl={false}
                value={value as T}
                disabled={disabled}
                required={false}
                autoFocus={true}
                name={definition.name}
                type={definition.fieldType}
                onChange={changeHandler}
                id={definition.id}
            />
        )}
    </Box>
}
