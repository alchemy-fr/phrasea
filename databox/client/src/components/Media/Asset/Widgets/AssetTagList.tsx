import React, {useContext} from 'react';
import {Tag} from "../../../../types";
import {Box} from "@mui/material";
import {DisplayContext} from "../../DisplayContext";
import TagNode, {tagClassName} from "../../../Ui/TagNode";

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

    const r = (c: Tag) => <TagNode
        size={'small'}
        key={c.id}
        name={c.name}
        color={c.color}
    />

    const rest = tags.length - (tagsLimit - 1);
    const others = tagsLimit > 1 ? `+ ${rest} other${rest > 1 ? 's' : ''}` : `${rest} tag${rest > 1 ? 's' : ''}`;

    const chips = tags.length <= tagsLimit ? tags.slice(0, tagsLimit).map(r) : [
        tags.slice(0, tagsLimit - 1).map(r),
        [<TagNode
            key={'o'}
            size={'small'}
            name={others}
            color={'#DDD'}
            title={tags.slice(tagsLimit - 1).map(c => c.name).join("\n")}
        />]
    ].flat();

    return <Box sx={{
        px: 1,
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        [`.${tagClassName}+.${tagClassName}`]: {
            ml: 0.5,
        }
    }}>
        {chips}
    </Box>
}
