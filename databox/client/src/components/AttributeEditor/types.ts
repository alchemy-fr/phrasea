import {Asset, AttributeDefinition} from "../../types.ts";

export type Values<T extends any = any> = {
    indeterminate?: boolean;
    values: T[];
    originalValues: T[];
}

export type AttributeValues = Record<string, Values>;

export type AssetValueIndex = Record<string, any>;
export type AttributesIndex = Record<string, AssetValueIndex>;

export type SuggestionTabProps = {
    definition: AttributeDefinition;
    valueContainer: Values;
    setAttributeValue: SetAttributeValue;
    subSelection: Asset[];
}

export type SetAttributeValue = (value: any, updateInput?: boolean) => void;
