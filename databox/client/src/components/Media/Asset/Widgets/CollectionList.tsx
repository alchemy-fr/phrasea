import React, {useContext} from 'react';
import {Collection} from "../../../../types";
import {Box} from "@mui/material";
import {DisplayContext} from "../../DisplayContext";
import {CollectionChip} from "../../../Ui/Chips";

type Props = {
    collections: Collection[];
};

export default function AssetCollectionList({
                                                collections,
                                            }: Props) {
    const {collectionsLimit, displayCollections} = useContext(DisplayContext)!;

    if (!displayCollections) {
        return <></>
    }

    const r = (c: Collection) => <CollectionChip
        size={'small'}
        key={c.id}
        label={c.title}
    />

    const rest = collections.length - (collectionsLimit - 1);
    const others = collectionsLimit > 1 ? `+ ${rest} other${rest > 1 ? 's' : ''}` : `${rest} collection${rest > 1 ? 's' : ''}`;

    const chips = collections.length <= collectionsLimit ? collections.slice(0, collectionsLimit).map(r) : [
        collections.slice(0, collectionsLimit - 1).map(r),
        [<CollectionChip
            key={'o'}
            size={'small'}
            label={others}
            title={collections.slice(collectionsLimit - 1).map(c => c.title).join("\n")}
        />]
    ].flat();

    return <Box sx={{
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
