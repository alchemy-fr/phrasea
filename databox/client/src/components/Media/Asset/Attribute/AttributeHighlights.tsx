import reactStringReplace from 'react-string-replace';
import React, {PropsWithChildren, ReactElement, ReactNode,} from 'react';
import {styled} from '@mui/material/styles';
import nl2br from 'react-nl2br';

type FreeNode = string | ReactNode | ReactNode[];

function replaceText(
    text: FreeNode,
    func: (text: string) => FreeNode,
    options: {
        props?: {};
        depth?: number;
        stopTags?: string[];
    } = {}
): FreeNode {
    if (typeof text === 'string') {
        return func(text);
    } else if (React.isValidElement(text)) {
        if (
            (options.stopTags ?? []).includes(
                (text as ReactElement<object, string>).type
            )
        ) {
            return text;
        }

        return React.cloneElement(
            text,
            options.props || {},
            replaceText(text.props.children, func, options)
        ) as ReactElement;
    } else if (Array.isArray(text)) {
        return text
            .map((e, i) =>
                replaceText(e, func, {
                    ...options,
                    depth: (options.depth ?? 0) + 1,
                    props: {
                        key: `${options.depth?.toString() ?? '0'}:${i}`,
                    },
                })
            )
            .flat();
    }

    return text;
}

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
