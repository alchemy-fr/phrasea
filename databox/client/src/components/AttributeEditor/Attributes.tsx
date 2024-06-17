import {AttributeDefinition, StateSetter} from "../../types";
import {Box, ListItem, ListItemButton} from "@mui/material";
import {AttributeValues} from "./types";
import {useTranslation} from 'react-i18next';

type Props = {
    values: AttributeValues;
    definition: AttributeDefinition | undefined;
    setDefinition: StateSetter<AttributeDefinition | undefined>;
    attributeDefinitions: AttributeDefinition[];
};

export default function Attributes({
    values,
    attributeDefinitions,
    definition,
    setDefinition,
}: Props) {
    const {t} = useTranslation();

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
                        {values[def.id].indeterminate ?
                            <span className={indeterminateClassName}>
                                {indeterminateLabel}
                            </span> : values[def.id].values[0] ?? ""}
                    </div>
                </ListItemButton>
            </ListItem>
        })}
    </Box>
}
