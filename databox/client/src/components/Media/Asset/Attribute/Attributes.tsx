import {Asset, AttributeDefinition, AttributeListItemType} from '../../../../types';
import React, {useContext, useMemo} from 'react';
import AttributeRowUI, {BaseAttributeRowUIProps} from './AttributeRowUI';
import {SxProps} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs';
import {AttributeFormatContext} from './Format/AttributeFormatContext';
import {AttributeGroup, buildAttributesGroupedByDefinition} from './attributeIndex.ts';
import {copyToClipBoardClass, copyToClipBoardContainerClass,} from './CopyAttribute.tsx';
import {hasDefinitionInItems, useAttributeListStore} from "../../../../store/attributeListStore.ts";
import {useTranslation} from 'react-i18next';
import {getBuiltInFilters, getIndexById} from "../../../../store/attributeDefinitionStore.ts";
import {NO_LOCALE} from "./AttributesEditor.tsx";
import Separator from "../../../Ui/Separator.tsx";
import {Spacer} from "../../../Ui/VerticalSpacer.tsx";
import {AttributeFormat} from "./types/types";


type AttributeItem = {
    id: string;
    type: AttributeListItemType;
    definition?: AttributeDefinition;
    attribute?: AttributeGroup['attribute'];
    format?: AttributeFormat;
    key?: string;
}

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
    const {t} = useTranslation();
    const formatContext = useContext(AttributeFormatContext);
    const definitionsIndex = getIndexById();
    const toggleDefinition = useAttributeListStore(s => s.toggleDefinition)
    const current = useAttributeListStore(s => s.current)

    const pinnedAttributes = current?.items ?? [];

    const attributeItems = useMemo<AttributeItem[]>(() => {
        let attributeGroups = buildAttributesGroupedByDefinition(asset.attributes);
        if (pinnedAttributes.length === 0) {
            return attributeGroups.map(ag => ({
                id: ag.definition.id,
                type: AttributeListItemType.Definition,
                attribute: ag.attribute,
                definition: ag.definition,
            }))
        }

        const attributeItems: AttributeItem[] = [];

        const builtInDef = getBuiltInFilters(t);

        pinnedAttributes.forEach(item => {
            const props = {
                id: item.id,
                type: item.type,
                format: item.format,
            }

            if (item.type === AttributeListItemType.Definition) {
                const defId = item.definition!;
                const group = attributeGroups.find(g => g.definition.id === defId);

                if (group) {
                    attributeItems.push({
                        ...props,
                        attribute: group.attribute,
                        definition: group.definition,
                    });
                } else if (item.displayEmpty && definitionsIndex[defId]) {
                    attributeItems.push({
                        ...props,
                        definition: definitionsIndex[defId],
                    });
                }
            } else if (item.type === AttributeListItemType.BuiltIn) {
                const definition = builtInDef.find(g => g.id === item.key!);

                if (definition) {
                    attributeItems.push({
                        ...props,
                        definition,
                    });
                }
            } else {
                attributeItems.push({
                    ...props,
                    key: item.key,
                });
            }
        });

        if (!pinnedOnly) {
            attributeGroups.filter(g =>
                !hasDefinitionInItems(pinnedAttributes, g.definition.id)
            ).forEach(ag => {
                attributeItems.push({
                    id: ag.definition.id,
                    type: AttributeListItemType.Definition,
                    attribute: ag.attribute,
                    definition: ag.definition,
                });
            });
        }

        return attributeItems;
    }, [pinnedAttributes, asset, t, definitionsIndex]);

    if (attributeItems.length === 0) {
        return null;
    }

    const createAttrProps = (definition: AttributeDefinition) => ({
        origin: 'machine',
        locale: NO_LOCALE,
        capabilities: {},
        definition,
    });

    return (
        <div
            onDoubleClick={stopPropagation}
            onClick={stopPropagation}
            onMouseDown={stopPropagation}
        >
            {attributeItems.map((ai) => {
                if (ai.type === AttributeListItemType.Definition) {
                    return <AttributeRowUI
                        key={ai.id}
                        formatContext={formatContext}
                        format={ai.format}
                        attribute={ai.attribute!}
                        definition={ai.definition!}
                        displayControls={displayControls}
                        pinned={hasDefinitionInItems(pinnedAttributes, ai.definition!.id)}
                        togglePin={toggleDefinition}
                        assetAnnotationsRef={assetAnnotationsRef}
                    />
                } else if (ai.type === AttributeListItemType.BuiltIn) {
                    const definition = ai.definition!;
                    if (definition.getValueFromAsset) {
                        const valueFromAsset = definition.getValueFromAsset(asset);

                        return <AttributeRowUI
                            key={ai.id}
                            formatContext={formatContext}
                            format={ai.format}
                            attribute={definition.multiple ? valueFromAsset.map((v: any) => ({
                                ...createAttrProps(definition),
                                value: v,
                            })) : {
                                ...createAttrProps(definition),
                                value: valueFromAsset,
                            }}
                            definition={ai.definition!}
                            displayControls={displayControls}
                            pinned={hasDefinitionInItems(pinnedAttributes, ai.definition!.id)}
                            togglePin={toggleDefinition}
                            assetAnnotationsRef={assetAnnotationsRef}
                        />
                    }
                } else if (ai.type === AttributeListItemType.Divider) {
                    return (<Separator
                        key={ai.id}
                    >{ai.key!}</Separator>)
                } else if (ai.type === AttributeListItemType.Spacer) {
                    return <Spacer
                        key={ai.id}
                    />
                }

                return null;
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
