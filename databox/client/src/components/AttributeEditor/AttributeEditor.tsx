import {Asset, AttributeDefinition} from "../../types.ts";
import {Box} from "@mui/material";
import React from "react";
import ThumbList from "./ThumbList.tsx";
import {AssetSelectionContext} from "../../context/AssetSelectionContext.tsx";
import DisplayProvider from "../Media/DisplayProvider.tsx";
import {OnToggle} from "../AssetList/types.ts";
import {getItemListFromEvent} from "../AssetList/selection.ts";
import Attributes from "./Attributes.tsx";
import {useAttributeValues} from "./attributeGroup.ts";
import DefinitionsSkeleton from "./DefinitionsSkeleton.tsx";
import SuggestionPanel from "./Suggestions/SuggestionPanel.tsx";
import {scrollbarWidth} from "../../constants.ts";
import EditorPanel from "./EditorPanel.tsx";
import {SetAttributeValue} from "./types.ts";
import {NO_LOCALE} from "../Media/Asset/Attribute/AttributesEditor.tsx";

type Props = {
    assets: Asset[];
    attributeDefinitions: AttributeDefinition[];
};

export default function AttributeEditor({
    assets,
    attributeDefinitions,
}: Props) {
    const [subSelection, setSubSelection] = React.useState<Asset[]>([]);
    const [definition, setDefinition] = React.useState<AttributeDefinition | undefined>();
    const [thumbSize, _setThumbSize] = React.useState(200);
    const thumbsHeight = thumbSize + scrollbarWidth;
    const [locale, setLocale] = React.useState<string>('en');
    const {values, setValue, inputValueInc} = useAttributeValues(
        attributeDefinitions,
        assets,
        subSelection,
    );

    const definitionLocale = definition?.translatable ? locale : NO_LOCALE;
    const value = definition && values[definition.id] ? values[definition.id] : undefined;

    React.useEffect(() => {
        setSubSelection(assets);
    }, [assets]);

    const onToggleAsset = React.useCallback<OnToggle<Asset>>(
        (asset, e): void => {
            e?.preventDefault();
            setSubSelection(prev => {
                return getItemListFromEvent(prev, asset, [assets!], e);
            });
        },
        [assets]
    );

    const setAttributeValue = React.useCallback<SetAttributeValue>((value, updateInput) => {
        if (definition) {
            setValue(definition.id, definitionLocale, value, updateInput);
        }
    }, [definition, setValue, definitionLocale]);

    return <Box
        sx={{
            display: 'flex',
            flexDirection: 'column',
            height: '100vh',
            overflow: 'hidden',
        }}
    >
        <DisplayProvider
            thumbSize={thumbSize}
        >
            <AssetSelectionContext.Provider
                value={{
                    selection: subSelection!,
                    setSelection: setSubSelection,
                }}
            >
                <div style={{
                    height: thumbsHeight,
                }}>
                    <ThumbList
                        assets={assets}
                        onToggle={onToggleAsset}
                        subSelection={subSelection}
                    />
                </div>
                <Box sx={{
                    flexGrow: 1,
                    flexShrink: 1,
                    height: `calc(100vh - ${thumbsHeight}px)`,
                    overflow: 'hidden',
                }}>
                    <Box
                        sx={{
                            display: 'flex',
                            flexGrow: 1,
                            maxHeight: '100%',
                            '> div': {
                                maxHeight: '100%',
                                overflow: 'auto',
                            }
                        }}
                    >
                        <Box sx={{
                            width: 300,
                            'strong': {
                                mr: 1,
                                verticalAlign: 'top',
                                alignSelf: 'start',
                            }
                        }}>
                            {attributeDefinitions ? <Attributes
                                attributeDefinitions={attributeDefinitions}
                                values={values}
                                setDefinition={setDefinition}
                                definition={definition}
                                locale={locale}
                            /> : <DefinitionsSkeleton/>}
                        </Box>

                        <Box sx={{
                            flexGrow: 1,
                        }}>
                            {value && definition ? <EditorPanel
                                inputValueInc={inputValueInc}
                                definition={definition}
                                valueContainer={value}
                                subSelection={subSelection}
                                setLocale={setLocale}
                                locale={definitionLocale}
                                setAttributeValue={setAttributeValue}
                            /> : ''}
                        </Box>
                        <Box sx={{
                            width: 500,
                        }}>
                            {definition && value ? <SuggestionPanel
                                locale={definitionLocale}
                                valueContainer={value}
                                definition={definition}
                                setAttributeValue={setAttributeValue}
                                subSelection={subSelection}
                            /> : ''}
                        </Box>
                    </Box>
                </Box>
            </AssetSelectionContext.Provider>
        </DisplayProvider>
    </Box>
}
