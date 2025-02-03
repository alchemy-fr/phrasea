import reactStringReplace from 'react-string-replace';
import React, {PropsWithChildren} from 'react';
import {styled} from '@mui/material/styles';
import nl2br from 'react-nl2br';
import {FreeNode, replaceText} from "../../../../lib/reactText.tsx";

const Highlight = styled('em')(({theme}) => ({
    backgroundColor: theme.palette.warning.main,
    color: theme.palette.warning.contrastText,
    padding: '1px 3px',
    margin: '-1px -3px',
    borderRadius: 3,
}));

export function replaceHighlight(
    value?: string,
    Compoment: React.FunctionComponent<PropsWithChildren<any>> = Highlight
): FreeNode {
    if (!value) {
        return [];
    }

    const replaced = reactStringReplace(
        value,
        /\[hl](.*?)\[\/hl]/g,
        (m, index) => {
            return <Compoment key={index}>{m}</Compoment>;
        }
    );

    return replaceText(replaced, nl2br);
}
