import React from "react";
import AttributeWidget from "./AttributeWidget";
import {AttributeDefinition} from "../../../../types";
import {AttrValue, LocalizedAttributeIndex, NO_LOCALE, OnChangeHandler} from "./AttributesEditor";
import MultiAttributeRow from "./MultiAttributeRow";
import FormRow from "../../../Form/FormRow";
import {FormLabel} from "@mui/material";
import TranslatableAttributeTabs from "./TranslatableAttributeTabs";

function extractNoLocaleOrDefinedLocaleValue<T>(attributes: LocalizedAttributeIndex<T>): AttrValue<T> | AttrValue<T>[] | undefined {
    if (attributes.hasOwnProperty(NO_LOCALE)) {
        return attributes[NO_LOCALE];
    }

    const locales = Object.keys(attributes);
    if (locales.length >= 1) {
        return attributes[locales[0]];
    }
}

type Props = {
    definition: AttributeDefinition;
    attributes: LocalizedAttributeIndex<string | number>;
    disabled: boolean;
    onChange: OnChangeHandler;
    indeterminate?: boolean;
    readOnly?: boolean;
    autoFocus?: boolean;
    currentLocale: string;
    onLocaleChange: (locale: string) => void;
}

export default function AttributeType({
                                          definition,
                                          readOnly,
                                          attributes,
                                          disabled,
                                          onChange,
                                          indeterminate,
                                          currentLocale,
                                          autoFocus,
                                          onLocaleChange,
                                      }: Props) {

    const changeHandler = React.useCallback((locale: string, value: AttrValue<string | number> | AttrValue<string | number>[] | undefined) => {
        onChange(definition.id, locale, value);
    }, [onChange]);

    if (definition.translatable) {
        return <>
            <FormRow>
                <FormLabel>
                    {definition.name}
                </FormLabel>
                <TranslatableAttributeTabs
                    currentLocale={currentLocale}
                    onLocaleChange={onLocaleChange}
                    definition={definition}
                    changeHandler={changeHandler}
                    attributes={attributes}
                    readOnly={readOnly}
                    disabled={disabled}
                />
            </FormRow>
        </>
    }

    return <FormRow>
        {definition.multiple ? <MultiAttributeRow
            indeterminate={indeterminate}
            readOnly={readOnly}
            isRtl={false}
            disabled={disabled}
            type={definition.fieldType}
            name={definition.name}
            values={(extractNoLocaleOrDefinedLocaleValue(attributes) || []) as AttrValue<string | number>[]}
            onChange={(v) => changeHandler(NO_LOCALE, v)}
            id={definition.id}
        /> : <AttributeWidget
            indeterminate={indeterminate}
            readOnly={readOnly}
            isRtl={false}
            autoFocus={autoFocus}
            value={extractNoLocaleOrDefinedLocaleValue(attributes) as AttrValue<string | number> | undefined}
            required={false}
            disabled={disabled}
            name={definition.name}
            type={definition.fieldType}
            onChange={(v) => changeHandler(NO_LOCALE, v)}
            id={definition.id}
        />}
    </FormRow>
}
