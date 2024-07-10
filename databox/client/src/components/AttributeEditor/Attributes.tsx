import {AttributeDefinition, StateSetter} from '../../types';
import {List, ListItem, ListItemButton, ListItemIcon} from '@mui/material';
import {DefinitionValuesIndex} from './types';
import {useTranslation} from 'react-i18next';
import {getAttributeType} from '../Media/Asset/Attribute/types';
import {useContext} from 'react';
import {AttributeFormatContext} from '../Media/Asset/Attribute/Format/AttributeFormatContext.ts';
import {AttributeFormatterProps} from '../Media/Asset/Attribute/types/types';
import {NO_LOCALE} from '../Media/Asset/Attribute/AttributesEditor.tsx';
import LockIcon from '@mui/icons-material/Lock';

type Props = {
    definitionValues: DefinitionValuesIndex<any>;
    definition: AttributeDefinition | undefined;
    setDefinition: StateSetter<AttributeDefinition | undefined>;
    attributeDefinitions: AttributeDefinition[];
    locale: string;
};

export default function Attributes({
    definitionValues,
    attributeDefinitions,
    definition,
    setDefinition,
    locale,
}: Props) {
    const {t} = useTranslation();
    const formatContext = useContext(AttributeFormatContext);

    const indeterminateClassName = 'def-indeter';
    const indeterminateLabel = t(
        'attribute_editor.definitions.indeterminate',
        'Indeterminate'
    );

    return (
        <List
            sx={{
                [`.${indeterminateClassName}`]: {
                    color: 'warning.main',
                },
            }}
        >
            {attributeDefinitions.map(def => {
                const l = def?.translatable ? locale : NO_LOCALE;
                const type = def.fieldType;
                const formatter = getAttributeType(type);
                const defValue = definitionValues[def.id];
                const valueFormatterProps: AttributeFormatterProps = {
                    value: defValue.value?.[l] ?? '',
                    locale,
                    format: formatContext.formats[type],
                };

                return (
                    <ListItem disablePadding key={def.id}>
                        <ListItemButton
                            selected={definition === def}
                            onClick={() => setDefinition(def)}
                        >
                            {!def.canEdit ? (
                                <ListItemIcon>
                                    <LockIcon/>
                                </ListItemIcon>
                            ) : (
                                ''
                            )}
                            <strong>{def.name}</strong>
                            <div>
                                {defValue.indeterminate.g ? (
                                    <span className={indeterminateClassName}>
                                        {indeterminateLabel}
                                    </span>
                                ) : !def.multiple ? (
                                    formatter.formatValue(valueFormatterProps)
                                ) : <>
                                    {(defValue.value?.[l]?.length) ?? 0}
                                    {' values'}
                                </>}
                            </div>
                        </ListItemButton>
                    </ListItem>
                );
            })}
        </List>
    );
}
