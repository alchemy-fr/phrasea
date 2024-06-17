import {AttributeDefinition, StateSetter} from "../../types";
import {ListItem, ListItemButton} from "@mui/material";
import {AttributeValues} from "./types";

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
    return <>
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
    </>
}
