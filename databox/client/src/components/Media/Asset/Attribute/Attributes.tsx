import {Asset} from '../../../../types';
import React, {useContext} from 'react';
import AttributeRowUI, {BaseAttributeRowUIProps} from './AttributeRowUI';
import {SxProps} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs';
import {UserPreferencesContext} from '../../../User/Preferences/UserPreferencesContext';
import {AttributeFormatContext} from './Format/AttributeFormatContext';
import {buildAttributesGroupedByDefinition} from './attributeIndex.ts';
import {
    copyToClipBoardClass,
    copyToClipBoardContainerClass,
} from './CopyAttribute.tsx';
import {AssetAnnotation} from '../Annotations/annotationTypes.ts';
import {hasDefinitionInItems, useAttributeListStore} from "../../../../store/attributeListStore.ts";

export type OnActiveAnnotations = (annotations: AssetAnnotation[]) => void;

type Props = {
    asset: Asset;
    displayControls: boolean;
    pinnedOnly?: boolean;
} & BaseAttributeRowUIProps;

function Attributes({
    asset,
    displayControls,
    pinnedOnly,
    assetAnnotationsRef,
}: Props) {
    const formatContext = useContext(AttributeFormatContext);
    const toggleDefinition = useAttributeListStore(s => s.toggleDefinition)
    const current = useAttributeListStore(s => s.current)

    const pinnedAttributes = current?.items ?? [];

    let attributeGroups = buildAttributesGroupedByDefinition(asset.attributes);

    attributeGroups.sort((a, b) => {
        const aa = hasDefinitionInItems(pinnedAttributes, a.definition.id) ? 1 : 0;
        const bb = hasDefinitionInItems(pinnedAttributes, b.definition.id) ? 1 : 0;

        return bb - aa;
    });

    if (pinnedOnly) {
        attributeGroups = attributeGroups.filter(g =>
            hasDefinitionInItems(pinnedAttributes, g.definition.id)
        );
    }

    if (attributeGroups.length === 0) {
        return null;
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
                        pinned={hasDefinitionInItems(pinnedAttributes, g.definition.id)}
                        togglePin={toggleDefinition}
                        assetAnnotationsRef={assetAnnotationsRef}
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
        [`.${copyToClipBoardContainerClass} .${copyToClipBoardClass}`]: {
            visibility: 'hidden',
            ml: 2,
        },
        [`.${copyToClipBoardContainerClass}:hover .${copyToClipBoardClass}`]: {
            visibility: 'visible',
        },
    };
}
