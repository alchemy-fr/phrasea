import AttributeWidget, {
    createWidgetOptionsFromDefinition,
} from './AttributeWidget';
import {AttrValue, LocalizedAttributeIndex} from './AttributesEditor';
import MultiAttributeRow from './MultiAttributeRow';
import TranslatableAttributeTabs from './TranslatableAttributeTabs';
import React from 'react';
import {NO_LOCALE} from './constants.ts';
import {AttributeTypeProps} from './attributeTypes.ts';

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

export default function AttributeType({
    definition,
    attributes,
    onChange,
    ...attributeProps
}: AttributeTypeProps) {
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
            <TranslatableAttributeTabs
                definition={definition}
                changeHandler={changeHandler}
                attributes={attributes}
                options={createWidgetOptionsFromDefinition(definition)}
                {...attributeProps}
            />
        );
    }

    return (
        <>
            {definition.multiple ? (
                <MultiAttributeRow
                    isRtl={false}
                    type={definition.fieldType}
                    label={definition.nameTranslated ?? definition.name}
                    values={
                        (extractNoLocaleOrDefinedLocaleValue(attributes) ||
                            []) as AttrValue<string | number>[]
                    }
                    onChange={v => changeHandler(NO_LOCALE, v)}
                    id={definition.id}
                    options={createWidgetOptionsFromDefinition(definition)}
                    {...attributeProps}
                />
            ) : (
                <AttributeWidget
                    isRtl={false}
                    value={
                        extractNoLocaleOrDefinedLocaleValue(attributes) as
                            | AttrValue<string | number>
                            | undefined
                    }
                    required={false}
                    label={definition.nameTranslated ?? definition.name}
                    type={definition.fieldType}
                    onChange={v => changeHandler(NO_LOCALE, v)}
                    id={definition.id}
                    options={createWidgetOptionsFromDefinition(definition)}
                    {...attributeProps}
                />
            )}
        </>
    );
}
