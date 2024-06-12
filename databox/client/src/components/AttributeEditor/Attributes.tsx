import {Asset, AttributeDefinition} from "../../types.ts";
import {useAttributeValues} from "./attributeGroup.ts";
import {Box, ListItem, ListItemButton, TextField} from "@mui/material";
import React from "react";

type Props = {
    assets: Asset[];
    subSelection: Asset[];
    attributeDefinitions: AttributeDefinition[];
};

export default function Attributes({
    assets,
    attributeDefinitions,
    subSelection,
}: Props) {
    const {values, setValue} = useAttributeValues(attributeDefinitions, assets, subSelection);
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [definition, setDefinition] = React.useState<AttributeDefinition | undefined>(attributeDefinitions[0]);

    const value = definition ? values[definition.id] : undefined;

    React.useEffect(() => {
        inputRef.current?.focus();
    }, [definition]);

    return <div
        style={{
            display: 'flex',
            flexGrow: 1,
        }}
    >
        <Box sx={{
            width: 300,
            'strong': {
                mr: 1,
                verticalAlign: 'top',
                alignSelf: 'start',
            }
        }}>
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
                                <span style={{color: 'red'}}>Indeterminate</span> : values[def.id].values[0] ?? ""}
                        </div>
                    </ListItemButton>
                </ListItem>
            })}
        </Box>

        <Box sx={{
            flexGrow: 1,
        }}>
            {value ? <>
                <TextField
                    inputRef={inputRef}
                    autoFocus={true}
                    value={!value.indeterminate ? value.values[0] ?? '' : ''}
                    onChange={(e) => setValue(definition!.id, e.target.value)}
                    placeholder={value.indeterminate ? '-------' : undefined}
                />
            </> : ''}
        </Box>
    </div>
}
