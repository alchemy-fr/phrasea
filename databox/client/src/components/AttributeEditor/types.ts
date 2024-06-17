import {Asset, AttributeDefinition} from "../../types.ts";

export type Values<T extends any = any> = {
    definition: AttributeDefinition;
    indeterminate?: boolean;
    values: T[];
    originalValues: T[];
}

export type AttributeValues = Record<string, Values>;

export type LocalizedAttributeIndex<T = string> = {
    [locale: string]: T | undefined;
};

export type AssetAttributeIndex<T = string> = {
    [assetId: string]: LocalizedAttributeIndex<T>;
};

export type AttributeIndex<T = string> = {
    [definitionId: string]: AssetAttributeIndex<T>;
};

export type SuggestionTabProps = {
    definition: AttributeDefinition;
    valueContainer: Values;
    setAttributeValue: SetAttributeValue;
    subSelection: Asset[];
    currentLocale: string;
}

export type SetAttributeValue<T = string> = (value: T | undefined, updateInput?: boolean) => void;
