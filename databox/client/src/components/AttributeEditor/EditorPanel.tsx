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
    locale: string;
    setLocale: StateSetter<string>;
};

export default function EditorPanel<T>({
    definition,
    valueContainer,
    setAttributeValue,
    subSelection,
    inputValueInc,
    locale,
    setLocale,
}: Props<T>) {
    const disabled = false; // TODO
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [proxyValue, setValue] = React.useState<T | undefined>();
    const [currentDefinition, setCurrentDefinition] = React.useState(definition);

    const value = currentDefinition === definition ? proxyValue : undefined;

    React.useEffect(() => {
        inputRef.current?.focus();
    }, [definition, valueContainer, inputValueInc, locale]);

    React.useEffect(() => {
        setValue(valueContainer.indeterminate[locale] ? undefined : (valueContainer.values[0]?.[locale] ?? ''));
        setCurrentDefinition(definition);
    }, [definition, subSelection, inputValueInc, locale]);

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
            p: 3,
        }}
    >
        {definition.translatable && definition.locales ? <>
            <Tabs
                value={locale}
                onChange={(_e, l) => setLocale(l)}
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
                readOnly={readOnly}
                isRtl={false}
                disabled={disabled}
                type={definition.fieldType}
                name={definition.name}
                valueContainer={valueContainer}
                onChange={changeHandler}
                id={definition.id}
                locale={locale}
            />
        ) : (
            <AttributeWidget<T>
                indeterminate={valueContainer.indeterminate.g}
                readOnly={readOnly}
                isRtl={false}
                value={value}
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
