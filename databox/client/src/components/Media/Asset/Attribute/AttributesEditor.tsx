import {Box} from '@mui/material';
import {AttributeDefinition} from '../../../../types';
import AttributeType from './AttributeType';
import {toArray} from '../../../../lib/utils';
import React from 'react';

export type AttrValue<T = string> = {
    id: T;
    value: any;
};

export const NO_LOCALE = '_';
export type DefinitionIndex = Record<string, AttributeDefinition>;
export type LocalizedAttributeIndex<T = string> = {
    [locale: string]: AttrValue<T> | AttrValue<T>[] | undefined;
};
export type AttributeIndex<T = string> = {
    [definitionId: string]: LocalizedAttributeIndex<T>;
};

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

export type OnChangeHandler = (
    defId: string,
    locale: string,
    value: AttrValue<string | number> | AttrValue<string | number>[] | undefined
) => void;

type Props = {
    attributes: AttributeIndex<string | number>;
    definitions: DefinitionIndex;
    onChangeHandler: OnChangeHandler;
    disabled: boolean;
};

export default function AttributesEditor({
    attributes,
    definitions,
    onChangeHandler,
    disabled,
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

                return (
                    <Box
                        key={defId}
                        sx={{
                            mb: 5,
                        }}
                    >
                        <AttributeType
                            readOnly={!d.canEdit}
                            attributes={attributes[defId]}
                            disabled={disabled}
                            definition={d}
                            onChange={onChangeHandler}
                            onLocaleChange={setCurrentLocale}
                            currentLocale={currentLocale}
                        />
                    </Box>
                );
            })}
        </>
    );
}
