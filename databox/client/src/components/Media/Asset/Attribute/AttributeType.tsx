import AttributeWidget, {
    createWidgetOptionsFromDefinition,
} from './AttributeWidget';
import {AttributeDefinition} from '../../../../types';
import {
    AttrValue,
    LocalizedAttributeIndex,
    OnChangeHandler,
} from './AttributesEditor';
import MultiAttributeRow from './MultiAttributeRow';
import {FormRow} from '@alchemy/react-form';
import {FormLabel} from '@mui/material';
import TranslatableAttributeTabs from './TranslatableAttributeTabs';
import React from 'react';
import {NO_LOCALE} from './constants.ts';

function extractNoLocaleOrDefinedLocaleValue<T>(
    attributes: LocalizedAttributeIndex<T>
): AttrValue<T> | AttrValue<T>[] | undefined {
    // eslint-disable-next-line no-prototype-builtins
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
};

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
    const changeHandler = React.useCallback(
        (
            locale: string,
            value:
                | AttrValue<string | number>
                | AttrValue<string | number>[]
                | undefined
        ) => {
            onChange(definition.id, locale, value);
        },
        [onChange]
    );

    if (definition.translatable) {
        return (
            <>
                <FormRow>
                    <FormLabel>
                        {definition.nameTranslated ?? definition.name}
                    </FormLabel>
                    <TranslatableAttributeTabs
                        currentLocale={currentLocale}
                        onLocaleChange={onLocaleChange}
                        definition={definition}
                        changeHandler={changeHandler}
                        attributes={attributes}
                        readOnly={readOnly}
                        disabled={disabled}
                        options={createWidgetOptionsFromDefinition(definition)}
                    />
                </FormRow>
            </>
        );
    }

    return (
        <FormRow>
            {definition.multiple ? (
                <MultiAttributeRow
                    indeterminate={indeterminate}
                    readOnly={readOnly}
                    isRtl={false}
                    disabled={disabled}
                    type={definition.fieldType}
                    name={definition.nameTranslated ?? definition.name}
                    values={
                        (extractNoLocaleOrDefinedLocaleValue(attributes) ||
                            []) as AttrValue<string | number>[]
                    }
                    onChange={v => changeHandler(NO_LOCALE, v)}
                    id={definition.id}
                    options={createWidgetOptionsFromDefinition(definition)}
                />
            ) : (
                <AttributeWidget
                    indeterminate={indeterminate}
                    readOnly={readOnly}
                    isRtl={false}
                    autoFocus={autoFocus}
                    value={
                        extractNoLocaleOrDefinedLocaleValue(attributes) as
                            | AttrValue<string | number>
                            | undefined
                    }
                    required={false}
                    disabled={disabled}
                    name={definition.nameTranslated ?? definition.name}
                    type={definition.fieldType}
                    onChange={v => changeHandler(NO_LOCALE, v)}
                    id={definition.id}
                    options={createWidgetOptionsFromDefinition(definition)}
                />
            )}
        </FormRow>
    );
}
