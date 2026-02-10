import {FormLabel} from '@mui/material';
import {AssetTypeFilter, AttributeDefinition} from '../../../../types';
import AttributeType from './AttributeType';
import {toArray} from '../../../../lib/utils';
import React from 'react';
import {FormRow} from '@alchemy/react-form';
import {OnChangeHandler} from './attributeTypes.ts';

export type AttrValue<T = string> = {
    id: T;
    value: any;
};

export type DefinitionIndex = Record<string, AttributeDefinition>;
export type LocalizedAttributeIndex<T = string> = {
    [locale: string]: AttrValue<T> | AttrValue<T>[] | undefined;
};
export type AttributeIndex<T = string> = {
    [definitionId: string]: LocalizedAttributeIndex<T>;
};

type Props = {
    attributes: AttributeIndex<string | number>;
    definitions: DefinitionIndex;
    onChangeHandler: OnChangeHandler;
    disabled: boolean;
    assetTypeFilter: AssetTypeFilter;
};

export default function AttributesEditor({
    attributes,
    definitions,
    onChangeHandler,
    disabled,
    assetTypeFilter,
}: Props) {
    const defaultLocale = React.useMemo(() => {
        const firstTranslatableDefinition = toArray(definitions).find(
            d => d.translatable
        );
        if (firstTranslatableDefinition?.locales) {
            return firstTranslatableDefinition.locales[0];
        }
    }, [definitions]);

    const [currentLocale, setCurrentLocale] = React.useState<string>(
        defaultLocale ?? 'en'
    );

    return (
        <>
            {Object.keys(definitions).map(defId => {
                const d = definitions[defId];

                if (!d.editable || !d.editableInGui) {
                    return null;
                }

                if (assetTypeFilter && (d.target & assetTypeFilter) === 0) {
                    return null;
                }

                return (
                    <FormRow
                        key={defId}
                        sx={{
                            mb: 5,
                        }}
                    >
                        <FormLabel>{d.nameTranslated ?? d.name}</FormLabel>
                        <AttributeType
                            labelAlreadyRendered={true}
                            readOnly={!d.canEdit}
                            attributes={attributes[defId] || {}}
                            disabled={disabled}
                            definition={d}
                            onChange={onChangeHandler}
                            onLocaleChange={setCurrentLocale}
                            currentLocale={currentLocale}
                        />
                    </FormRow>
                );
            })}
        </>
    );
}
