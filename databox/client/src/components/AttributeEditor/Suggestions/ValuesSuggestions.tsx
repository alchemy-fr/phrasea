import {SuggestionTabProps} from "../types.ts";
import {ListItem, ListItemButton} from "@mui/material";

type Props = {
} & SuggestionTabProps;

export default function ValuesSuggestions({
    valueContainer,
    setAttributeValue,
}: Props) {

    return <>
        {valueContainer.values.map((v, index) => {
            return <ListItem
                key={index}
                disablePadding
            >
                <ListItemButton
                    onClick={() => setAttributeValue(v)}
                >
                    {v}
                </ListItemButton>
            </ListItem>
        })}
    </>
}
