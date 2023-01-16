import {Asset} from "../../../../types";
import reactStringReplace from 'react-string-replace';
import React, {PropsWithChildren, ReactElement, ReactNode} from "react";
import {styled} from "@mui/material/styles";
import AttributeRowUI from "./AttributeRowUI";
import {Box} from "@mui/material";
import {stopPropagation} from "../../../../lib/stdFuncs";

const nl2br = require('react-nl2br');

type FreeNode = string | ReactNode | ReactNode[];

function replaceText(text: FreeNode, func: (text: string) => FreeNode, options: {
    props?: {};
    stopTags?: string[];
} = {}): FreeNode {
    if (typeof text === 'string') {
        return func(text);
    } else if (React.isValidElement(text)) {
        if ((options.stopTags || []).includes((text as ReactElement<object, string>).type)) {
            return text;
        }

        return React.cloneElement(text, options.props || {}, replaceText(text.props.children, func, options)) as ReactElement;
    } else if (Array.isArray(text)) {
        return text.map((e, key) => replaceText(e, func, {
            ...options,
            props: {
                key,
            },
        })).flat();
    }

    return text;
}

const Highlight = styled("em")(({theme}) => ({
    backgroundColor: theme.palette.warning.main,
    color: theme.palette.warning.contrastText,
    padding: '1px 3px',
    margin: '-1px -3px',
    borderRadius: 3
}));

export function replaceHighlight(value?: string): FreeNode {
    if (!value) {
        return [];
    }

    const replaced = reactStringReplace(value, /\[hl](.*?)\[\/hl]/g, (m, index) => {
        return <Highlight
            key={index}
        >{m}</Highlight>;
    });

    return replaceText(replaced, nl2br);
}

type Props = PropsWithChildren<{
    asset: Asset;
}>

export default function Attributes({
                                       asset,
                                       children,
                                   }: Props) {
    return <Box
        sx={{
            '.attr-name': {
                fontWeight: 100,
                fontSize: 13,
            },
            '.attr-val': {
                mb: 2,
            },
            'ul': {
                m: 0,
                pl: 2,
            }
        }}
        onDoubleClick={stopPropagation}
        onClick={stopPropagation}
        onMouseDown={stopPropagation}
    >
        {asset.attributes.map(a => <AttributeRowUI
            key={a.id}
            value={a.value}
            attributeName={a.definition.name}
            type={a.definition.fieldType}
            locale={a.locale}
            highlight={a.highlight}
            multiple={a.multiple}
        />)}
        {children}
    </Box>
}
