import React, {useContext} from 'react';
import {Tag} from "../../../../types";
import {Box} from "@mui/material";
import {DisplayContext} from "../../DisplayContext";
import {TagChip} from "../../../Ui/Chips";
import LocalOfferIcon from '@mui/icons-material/LocalOffer';

type Props = {
    tags: Tag[];
};

export default function AssetTagList({
                                         tags,
                                     }: Props) {
    const {tagsLimit, displayTags} = useContext(DisplayContext)!;

    if (!displayTags) {
        return <></>
    }

    const r = (c: Tag) => <TagChip
        size={'small'}
        key={c.id}
        label={c.name}
    />

    const rest = tags.length - (tagsLimit - 1);
    const others = tagsLimit > 1 ? `+ ${rest} other${rest > 1 ? 's' : ''}` : `${rest} tag${rest > 1 ? 's' : ''}`;

    const chips = tags.length <= tagsLimit ? tags.slice(0, tagsLimit).map(r) : [
        tags.slice(0, tagsLimit - 1).map(r),
        [<TagChip
            key={'o'}
            size={'small'}
            label={others}
            title={tags.slice(tagsLimit - 1).map(c => c.name).join("\n")}
        />]
    ].flat();

    return <Box sx={{
        pr: 1,
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        '.MuiChip-root': {
            my: 0.5,
        }
    }}>
        {chips}
    </Box>
}
