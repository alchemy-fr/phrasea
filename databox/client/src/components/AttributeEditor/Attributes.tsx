import {AttributeDefinition, StateSetter} from "../../types";
import {Box, ListItem, ListItemButton} from "@mui/material";
import {AttributeValues} from "./types";
import {useTranslation} from 'react-i18next';
import {getAttributeType} from "../Media/Asset/Attribute/types";
import {useContext} from "react";
import {AttributeFormatContext} from "../Media/Asset/Attribute/Format/AttributeFormatContext.ts";
import {AttributeFormatterProps} from "../Media/Asset/Attribute/types/types";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";

type Props = {
    values: AttributeValues;
    definition: AttributeDefinition | undefined;
    setDefinition: StateSetter<AttributeDefinition | undefined>;
    attributeDefinitions: AttributeDefinition[];
    locale: string;
};

export default function Attributes({
    values,
    attributeDefinitions,
    definition,
    setDefinition,
    locale,
}: Props) {
    const {t} = useTranslation();
    const formatContext = useContext(AttributeFormatContext);

    const indeterminateClassName = 'def-indeter';
    const indeterminateLabel = t('attribute_editor.definitions.indeterminate', 'Indeterminate')

    return <Box
        sx={{
            [`.${indeterminateClassName}`]: {
                color: 'warning.main',
            }
        }}
    >
        {attributeDefinitions.map((def) => {
            const l = def?.translatable ? locale : NO_LOCALE;
            const type = def.fieldType;
            const formatter = getAttributeType(type);

            const valueFormatterProps: AttributeFormatterProps = {
                value: values[def.id].values[0]?.[l] ?? "",
                locale,
                multiple: def.multiple,
                format: formatContext.formats[type],
            };

            return <ListItem
                disablePadding
                key={def.id}
            >
                <ListItemButton
                    selected={definition === def}
                    onClick={() => setDefinition(def)}
                >
                    <strong>
                        {def.name}
                    </strong>
                    <div>
                        {values[def.id].indeterminate.g ?
                            <span className={indeterminateClassName}>
                                {indeterminateLabel}
                            </span> : formatter.formatValue(valueFormatterProps)}
                    </div>
                </ListItemButton>
            </ListItem>
        })}
    </Box>
}
