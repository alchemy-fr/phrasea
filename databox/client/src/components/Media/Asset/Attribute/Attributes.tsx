import {Asset, Attribute} from "../../../../types";
import reactStringReplace from 'react-string-replace';
import React, {ReactElement, ReactNode, ReactNodeArray} from "react";
import {isRtlLocale} from "../../../../lib/lang";

const nl2br = require('react-nl2br');

type FreeNode = string | ReactNode | ReactNodeArray;

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

export function replaceHighlight(value?: string): FreeNode {
    if (!value) {
        return [];
    }

    const replaced = reactStringReplace(value, /\[hl](.*?)\[\/hl]/g, (m, index) => {
        return <em
            className="hl"
            key={index}
        >{m}</em>;
    });

    return replaceText(replaced, nl2br);
}

function AttributeRow({
                          definition,
                          value,
                          highlight,
                          locale,
                      }: Attribute) {
    const finalValue = highlight || value;

    const isRtl = isRtlLocale(locale);

    return <div
        style={isRtl ? {
            direction: 'rtl'
        } : undefined}>
        <b>{definition.name}</b>
        {' '}
        <span
            lang={locale}
        >
            {finalValue && Array.isArray(finalValue)
                ? <ul>{finalValue.map((v, i) => <li key={i}>
                    {replaceHighlight(v)}
                </li>)}</ul> : replaceHighlight(finalValue)}
        </span>
    </div>
}

type Props = {
    asset: Asset;
}

export default function Attributes({
                                       asset,
                                   }: Props) {
    return <div className={'attributes'}>
        <div
            className={'attr-title'}>{asset.titleHighlight ? replaceHighlight(asset.titleHighlight) : asset.title}</div>
        {asset.attributes.map(a => <AttributeRow
            {...a}
            key={a.id}
        />)}
    </div>
}
