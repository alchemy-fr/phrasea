import React from "react";
import AttributeWidget from "./AttributeWidget";
import {AttributeDefinition} from "../../../../types";
import {AttrValue, LocalizedAttributeIndex, OnChangeHandler} from "./AttributesEditor";
import MultiAttributeRow from "./MultiAttributeRow";
import {NO_LOCALE} from "../EditAssetAttributes";
import {isRtlLocale} from "../../../../lib/lang";
import FormRow from "../../../Form/FormRow";

type Props = {
    definition: AttributeDefinition;
    attributes: LocalizedAttributeIndex<string | number>;
    disabled: boolean;
    onChange: OnChangeHandler;
}

export default function AttributeType({
                                          definition,
                                          attributes,
                                          disabled,
                                          onChange,
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
            {definition.locales!.map(locale => {
                const label = `${definition.name} ${locale.toUpperCase()}`;

                return <FormRow>
                    {definition.multiple ? <MultiAttributeRow
                        disabled={disabled}
                        type={definition.fieldType}
                        isRtl={isRtlLocale(locale)}
                        name={label}
                        values={(attributes[locale] || []) as AttrValue<string | number>[]}
                        onChange={(values) => changeHandler(locale, values)}
                        id={definition.id}
                    /> : <AttributeWidget
                        value={attributes[locale] as AttrValue<string | number> | undefined}
                        disabled={disabled}
                        type={definition.fieldType}
                        isRtl={isRtlLocale(locale)}
                        name={label}
                        required={false}
                        onChange={(v) => changeHandler(locale, v)}
                        id={definition.id}
                    />}
                </FormRow>
            })}
        </>
    }

    return <FormRow>
        {definition.multiple ? <MultiAttributeRow
            isRtl={false}
            disabled={disabled}
            type={definition.fieldType}
            name={definition.name}
            values={(attributes[NO_LOCALE] || []) as AttrValue<string | number>[]}
            onChange={(values) => changeHandler(NO_LOCALE, values)}
            id={definition.id}
        /> : <AttributeWidget
            isRtl={false}
            value={attributes[NO_LOCALE] as AttrValue<string | number> | undefined}
            required={false}
            disabled={disabled}
            name={definition.name}
            type={definition.fieldType}
            onChange={(v) => changeHandler(NO_LOCALE, v)}
            id={definition.id}
        />}
    </FormRow>
}
