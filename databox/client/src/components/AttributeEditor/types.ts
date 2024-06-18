import {Asset, AttributeDefinition} from "../../types.ts";

export type Values<T = any> = {
    definition: AttributeDefinition;
    indeterminate: {
        g: boolean;
    } & LocalizedAttributeIndex<boolean>;
    values: LocalizedAttributeIndex<T>[];
    originalValues: LocalizedAttributeIndex<T>[];
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
    locale: string;
}

export type SetAttributeValue<T = string> = (value: T | undefined, updateInput?: boolean) => void;

export type MultiValueValue<T> = {
    value: T;
    part: number;
    key: string;
}

export type MultiValueIndex<T> = {
    [key: string]: {
        p: number;
        v: T;
    };
}
