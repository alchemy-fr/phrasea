import {Asset, Attribute} from "../../../../types";
import reactStringReplace from 'react-string-replace';
import React, {ReactElement, ReactNode, useContext} from "react";
import {styled} from "@mui/material/styles";
import AttributeRowUI from "./AttributeRowUI";
import {Box} from "@mui/material";
import {stopPropagation} from "../../../../lib/stdFuncs";
import {UserPreferencesContext} from "../../../User/Preferences/UserPreferencesContext";

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

type Props = {
    asset: Asset;
    controls: boolean;
    pinnedOnly?: boolean;
};

export default function Attributes({
    asset,
    controls,
    pinnedOnly,
}: Props) {
    const {preferences, updatePreference} = useContext(UserPreferencesContext);

    const togglePin = React.useCallback((definitionId: string) => {
        updatePreference('pinnedAttrs', prev => {
            const ws = {...prev};

            if (ws[asset.workspace.id]?.includes(definitionId)) {
                ws[asset.workspace.id] = ws[asset.workspace.id].filter(c => c !== definitionId);
            } else {
                ws[asset.workspace.id] = [
                    ...(ws[asset.workspace.id] || []),
                    definitionId,
                ];
            }

            return ws;
        });
    }, []);

    const pinnedAttributes = (preferences.pinnedAttrs ?? {})[asset.workspace.id] ?? [];

    const attributes = asset.attributes;

    const sortedAttributes: Attribute[] = [];
    pinnedAttributes.forEach((defId) => {
        const i = asset.attributes.findIndex(a => a.definition.id === defId);
        if (i >= 0) {
            sortedAttributes.push(asset.attributes[i]);
        }
    });

    if (!pinnedOnly) {
        asset.attributes.forEach(a => {
            if (!sortedAttributes.some(sa => sa.definition.id === a.definition.id)) {
                sortedAttributes.push(a);
            }
        });
    }

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
        {sortedAttributes.map(a => <AttributeRowUI
            key={a.id}
            definitionId={a.definition.id}
            value={a.value}
            attributeName={a.definition.name}
            type={a.definition.fieldType}
            locale={a.locale}
            highlight={a.highlight}
            multiple={a.multiple}
            controls={controls}
            pinnedAttributes={pinnedAttributes}
            togglePin={togglePin}
        />)}
    </Box>
}
