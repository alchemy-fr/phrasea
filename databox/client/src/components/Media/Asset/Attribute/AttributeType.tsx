import React from "react";
import AttributeWidget from "./AttributeWidget";
import {AttributeDefinition} from "../../../../types";
import {AttrValue, LocalizedAttributeIndex, OnChangeHandler} from "./AttributesEditor";
import MultiAttributeRow from "./MultiAttributeRow";
import {NO_LOCALE} from "../EditAssetAttributes";

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
                                      }: Props) {
    const onChange = (v: any) => {
        console.log('v', v);
    };

    console.log('attributes', attributes);

    if (definition.translatable) {
        return <>
            {definition.locales!.map(locale => {
                return <div className={'form-group'}>
                    <b>{locale}</b>
                    {definition.multiple ? <MultiAttributeRow
                        disabled={disabled}
                        type={definition.type}
                        name={definition.name}
                        values={(attributes[locale] || []) as AttrValue<string | number>[]}
                        onChange={onChange}
                        id={definition.id}
                    /> : <AttributeWidget
                        value={attributes[locale] ? (attributes[locale] as AttrValue<string | number>).value : undefined}
                        disabled={disabled}
                        type={definition.type}
                        name={definition.name}
                        onChange={onChange}
                        id={definition.id}
                    />}
                </div>
            })}
        </>
    }

    return <div
        className={'form-group'}
    >
        {definition.multiple ? <MultiAttributeRow
            disabled={disabled}
            type={definition.type}
            name={definition.name}
            values={(attributes[NO_LOCALE] || []) as AttrValue<string | number>[]}
            onChange={onChange}
            id={definition.id}
        /> : <AttributeWidget
            value={attributes[NO_LOCALE] ? (attributes[NO_LOCALE] as AttrValue<string | number>).value : undefined}
            disabled={disabled}
            name={definition.name}
            type={definition.type}
            onChange={onChange}
            id={definition.id}
        />}
    </div>
}
