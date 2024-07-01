import {Asset} from '../../../../types';
import reactStringReplace from 'react-string-replace';
import React, {PropsWithChildren, ReactElement, ReactNode, useContext,} from 'react';
import {styled} from '@mui/material/styles';
import AttributeRowUI from './AttributeRowUI';
import {SxProps} from '@mui/material';
import nl2br from 'react-nl2br';
import {stopPropagation} from '../../../../lib/stdFuncs';
import {UserPreferencesContext} from '../../../User/Preferences/UserPreferencesContext';
import {AttributeFormatContext} from './Format/AttributeFormatContext';
import {buildAttributesGroupedByDefinition} from "./attributeIndex.ts";

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

type Props = {
    asset: Asset;
    displayControls: boolean;
    pinnedOnly?: boolean;
};

function Attributes({asset, displayControls, pinnedOnly}: Props) {
    const {preferences, updatePreference} = useContext(UserPreferencesContext);
    const formatContext = useContext(AttributeFormatContext);

    const togglePin = React.useCallback((definitionId: string) => {
        updatePreference('pinnedAttrs', prev => {
            const ws = {...prev};

            if (ws[asset.workspace.id]?.includes(definitionId)) {
                ws[asset.workspace.id] = ws[asset.workspace.id].filter(
                    c => c !== definitionId
                );
            } else {
                ws[asset.workspace.id] = [
                    ...(ws[asset.workspace.id] || []),
                    definitionId,
                ];
            }

            return ws;
        });
    }, []);

    const pinnedAttributes =
        (preferences.pinnedAttrs ?? {})[asset.workspace.id] ?? [];

    let attributeGroups = buildAttributesGroupedByDefinition(asset.attributes);

    attributeGroups.sort((a, b) => {
        const aa = pinnedAttributes.includes(a.definition.id) ? 1 : 0;
        const bb = pinnedAttributes.includes(b.definition.id) ? 1 : 0;

        return bb - aa;
    });

    if (pinnedOnly) {
        attributeGroups = attributeGroups.filter(g => pinnedAttributes.includes(g.definition.id));
    }

    return (
        <div
            onDoubleClick={stopPropagation}
            onClick={stopPropagation}
            onMouseDown={stopPropagation}
        >
            {attributeGroups.map(g => {
                return (
                    <AttributeRowUI
                        key={g.definition.id}
                        formatContext={formatContext}
                        attribute={g.attribute}
                        definition={g.definition}
                        displayControls={displayControls}
                        pinned={pinnedAttributes.includes(g.definition.id)}
                        togglePin={togglePin}
                    />
                );
            })}
        </div>
    );
}

export default React.memo(Attributes) as typeof Attributes;

export const attributesClasses = {
    controls: 'attr-ctls',
    name: 'attr-name',
    val: 'attr-val',
    list: 'attr-ul',
};

export function attributesSx(): SxProps {
    return {
        [`.${attributesClasses.name}`]: {
            fontWeight: 100,
            fontSize: 13,
            my: 0.5,
        },
        [`.${attributesClasses.controls}`]: {
            'display': 'inline-block',
            'ml': 1,
            'my': -1,
            '.MuiSvgIcon-root': {
                fontSize: 13,
            },
            '.MuiButtonBase-root + .MuiButtonBase-root': {
                ml: 1,
            },
        },
        [`.${attributesClasses.val}`]: {
            'mb': 1,
            'fontSize': 14,
            '.MuiSvgIcon-root': {
                fontSize: 13,
            },
        },
        [`.${attributesClasses.list}`]: {
            m: 0,
            pl: 1,
        },
    };
}
