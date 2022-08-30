import React from "react";
import AttributeWidget from "./AttributeWidget";
import {AttributeDefinition} from "../../../../types";
import {AttrValue, LocalizedAttributeIndex, NO_LOCALE, OnChangeHandler} from "./AttributesEditor";
import MultiAttributeRow from "./MultiAttributeRow";
import FormRow from "../../../Form/FormRow";
import {FormLabel} from "@mui/material";
import TranslatableAttributeTabs from "./TranslatableAttributeTabs";

type Props = {
    definition: AttributeDefinition;
    attributes: LocalizedAttributeIndex<string | number>;
    disabled: boolean;
    onChange: OnChangeHandler;
    indeterminate?: boolean;
    readOnly?: boolean;
    currentLocale: string;
    onLocaleChange: (locale: string) => void;
}

function extractNoLocaleOrDefinedLocaleValue<T>(attributes: LocalizedAttributeIndex<T>): AttrValue<T> | AttrValue<T>[] | undefined {
    if (attributes.hasOwnProperty(NO_LOCALE)) {
        return attributes[NO_LOCALE];
    }

    const locales = Object.keys(attributes);
    if (locales.length >= 1) {
        return attributes[locales[0]];
    }
}

export default function AttributeType({
                                          definition,
                                          readOnly,
                                          attributes,
                                          disabled,
                                          onChange,
                                          indeterminate,
                                          currentLocale,
                                          onLocaleChange,
                                      }: Props) {

    const changeHandler = (locale: string, v: AttrValue<string | number> | AttrValue<string | number>[] | undefined) => {
        const na = {...attributes};

        if (v && !(v instanceof Array)) {
            v = (v as AttrValue<string | number>).value ? v : undefined as any;
        }

        na[locale] = v;

        onChange(definition.id, na);
    };

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
            onChange={(values) => changeHandler(NO_LOCALE, values)}
            id={definition.id}
        /> : <AttributeWidget
            indeterminate={indeterminate}
            readOnly={readOnly}
            isRtl={false}
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
