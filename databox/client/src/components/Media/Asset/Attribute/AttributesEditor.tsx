import React from "react";
import {Box} from "@mui/material";
import {Attribute, AttributeDefinition} from "../../../../types";
import AttributeType from "./AttributeType";

export type AttrValue<T = string> = {
    id: T;
    value: any;
}

export const NO_LOCALE = '_';
export type DefinitionIndex = Record<string, AttributeDefinition>;
export type LocalizedAttributeIndex<T = string> = { [locale: string]: AttrValue<T> | AttrValue<T>[] | undefined };
export type AttributeIndex<T = string> = { [definitionId: string]: LocalizedAttributeIndex<T> };

let idInc = 1;

export function createNewValue(type: string): AttrValue<number> {
    switch (type) {
        default:
        case 'text':
            return {
                id: idInc++,
                value: '',
            };
    }
}

export function buildAttributeIndex(definitionIndex: DefinitionIndex, attributes: Attribute[]): AttributeIndex {
    const attributeIndex: AttributeIndex = {};
    Object.keys(definitionIndex).forEach((k) => {
        attributeIndex[definitionIndex[k].id] = {};
    });

    for (let a of attributes) {
        const def = definitionIndex[a.definition.id];
        if (!def) {
            continue;
        }

        const l = a.locale || NO_LOCALE;
        const v = {
            id: a.id,
            value: a.value,
        };

        if (!attributeIndex[a.definition.id]) {
            attributeIndex[a.definition.id] = {};
        }

        if (def.multiple) {
            if (!attributeIndex[a.definition.id][l]) {
                attributeIndex[a.definition.id][l] = [];
            }
            (attributeIndex[a.definition.id][l]! as AttrValue[]).push(v);
        } else {

            attributeIndex[a.definition.id][l] = v;
        }
    }

    return attributeIndex;
}

export type OnChangeHandler = (
    defId: string,
    locale: string,
    value: AttrValue<string | number> | AttrValue<string | number>[] | undefined
) => void;

type Props = {
    attributes: AttributeIndex<string | number>;
    definitions: DefinitionIndex;
    onChange: OnChangeHandler;
    disabled: boolean;
}

export default function AttributesEditor({
    attributes,
    definitions,
    onChange,
    disabled,
}: Props) {
    const [currentLocale, setCurrentLocale] = React.useState('fr_FR');

    return <>
        {Object.keys(definitions).map(defId => {
            const d = definitions[defId];

            return <Box
                key={defId}
                sx={{
                    mb: 5
                }}
            >
                <AttributeType
                    readOnly={!d.canEdit}
                    attributes={attributes[defId]}
                    disabled={disabled}
                    definition={d}
                    onChange={onChange}
                    onLocaleChange={setCurrentLocale}
                    currentLocale={currentLocale}
                />
            </Box>
        })}
    </>
}
