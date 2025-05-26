import {Asset, AttributeDefinition, StateSetter} from '../../types.ts';
import {AttributeType} from '../../api/attributes.ts';

export type IndeterminateGroup = {
    g: boolean;
} & LocalizedAttributeIndex<boolean>;

export type Values<T = any> = {
    definition: AttributeDefinition;
    indeterminate: IndeterminateGroup;
    values: LocalizedAttributeIndex<T>[];
    originalValues: LocalizedAttributeIndex<T>[];
};

export type AttributeDefinitionIndex = Record<string, AttributeDefinition>;

export type LocalizedAttributeIndex<T = string> = {
    [locale: string]: T | undefined;
};

export type AssetAttributeIndex<T = string> = {
    [assetId: string]: LocalizedAttributeIndex<T>;
};

export type BatchAttributeIndex<T = string> = {
    [definitionId: string]: AssetAttributeIndex<T>;
};

export type DefinitionValuesIndex<T> = {
    [definitionId: string]: {
        value: LocalizedAttributeIndex<T>;
        indeterminate: IndeterminateGroup;
    };
};

export type SuggestionTabProps<T> = {
    defaultPanelWidth: number;
    definition: AttributeDefinition;
    valueContainer: Values;
    setAttributeValue: SetAttributeValue<T>;
    assets: Asset[];
    subSelection: Asset[];
    setSubSelection: StateSetter<Asset[]>;
    locale: string;
    createToKey: CreateToKeyFunc<T>;
};

export type SetAttributeValueOptions = {
    updateInput?: boolean;
    add?: boolean;
    remove?: boolean;
};

export type SetAttributeValue<T = string> = (
    value: T | undefined,
    options?: SetAttributeValueOptions
) => void;

export type MultiValueValue<T> = {
    value: T;
    part: number;
    key: string;
};

export type MultiValueIndex<T> = {
    [key: string]: {
        p: number;
        v: T;
    };
};

export type CreateToKeyFunc<T = string> = (
    fieldType: AttributeType
) => ToKeyFuncTypeScoped<T>;

export type ToKeyFuncTypeScoped<T> = (v: T) => string;

export type AttributesCommit<T> = {
    index: BatchAttributeIndex<T>;
    subSelection: Asset[];
    definition: AttributeDefinition | undefined;
};

export type AttributesHistory<T> = {
    current: number;
    history: AttributesCommit<T>[];
};

export type DiffGroupIndex<T> = {
    [definitionId: string]: {
        [locale: string]: {
            [key: string]: {
                assetIds: string[];
                value: T;
                attributeIds?: string[];
            };
        };
    };
};

export enum ExtraAttributeDefinition {
    Tags = 'tags',
}

export type SelectedValue<T = any> = {
    value: T;
    key: string;
};
