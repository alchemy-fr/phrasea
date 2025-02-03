import React, {ReactElement, ReactNode} from "react";

export type FreeNode = string | ReactNode | ReactNode[];

export function replaceText(
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
