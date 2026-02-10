import {AttributeDefinition} from '../../../../types.ts';
import {AttrValue, LocalizedAttributeIndex} from './AttributesEditor.tsx';
import {AttributeWidgetOptions} from './types/types';
import {AttributeType} from '../../../../api/types.ts';

type BaseProps = {
    labelAlreadyRendered: boolean;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    autoFocus?: boolean;
};

type WrapperProps = {
    definition: AttributeDefinition;
    attributes: LocalizedAttributeIndex<string | number>;
    currentLocale: string;
    onLocaleChange: (locale: string) => void;
} & BaseProps;

export type OnChangeHandler = (
    defId: string,
    locale: string,
    value: AttrValue<string | number> | AttrValue<string | number>[] | undefined
) => void;

export type AttributeTypeProps = {
    onChange: OnChangeHandler;
    autoFocus?: boolean;
} & WrapperProps;

export type TranslatableAttributeTabsProps = {
    changeHandler: (
        locale: string,
        v: AttrValue<string | number> | AttrValue<string | number>[] | undefined
    ) => void;
    options: AttributeWidgetOptions;
} & WrapperProps;

export type MultiAttributeRowProps = {
    id: string;
    label: string;
    type: AttributeType;
    values: AttrValue<string | number>[];
    onChange: (values: AttrValue<string | number>[]) => void;
    isRtl: boolean;
    options: AttributeWidgetOptions;
} & BaseProps;

export type AttributeWidgetProps = {
    id: string;
    type: AttributeType;
    label: string;
    value: AttrValue<string | number> | undefined;
    required: boolean;
    isRtl: boolean;
    onChange: (value: AttrValue<string | number>) => void;
    options: AttributeWidgetOptions;
} & BaseProps;
