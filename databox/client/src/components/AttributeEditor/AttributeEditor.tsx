import {Asset, AttributeDefinition} from '../../types.ts';
import {Box, useTheme} from '@mui/material';
import React from 'react';
import {AssetSelectionContext} from '../../context/AssetSelectionContext.tsx';
import {ActionsContext} from '../AssetList/types.ts';
import Attributes from './Attributes.tsx';
import {useAttributeValues} from './attributeGroup.ts';
import DefinitionsSkeleton from './DefinitionsSkeleton.tsx';
import SuggestionPanel from './Suggestions/SuggestionPanel.tsx';
import {scrollbarWidth} from '../../constants.ts';
import EditorPanel from './EditorPanel.tsx';
import {SelectedValue, SetAttributeValue} from './types.ts';
import {Resizable} from 're-resizable';
import {useTranslation} from 'react-i18next';
import AttributesToolbar from './AttributesToolbar.tsx';
import {useSelectAllKey} from '../../hooks/useSelectAllKey.ts';
import {toast} from 'react-toastify';
import DisplayProvider from '../Media/DisplayProvider.tsx';
import AssetList from '../AssetList/AssetList.tsx';
import {ZIndex} from '../../themes/zIndex.ts';
import DeleteIcon from '@mui/icons-material/Delete';
import {useTabShortcut} from './shortcuts.ts';
import AssetToggleOverlay from './AssetToggleOverlay.tsx';
import {NO_LOCALE} from '../Media/Asset/Attribute/constants.ts';

type Props = {
    assets: Asset[];
    attributeDefinitions: AttributeDefinition[];
    onClose: () => void;
    removeFromSelection: (ids: string[]) => void;
};

export default function AttributeEditor({
    assets,
    attributeDefinitions: remoteAttributeDefinitions,
    onClose,
    removeFromSelection,
}: Props) {
    const {t} = useTranslation();

    const defaultSuggestionsPanelWidth = 500;
    const theme = useTheme();
    const [selectedValue, setSelectedValue] = React.useState<
        SelectedValue | undefined
    >();
    const [subSelection, setSubSelection] = React.useState<Asset[]>(assets);
    const [definition, setDefinition] = React.useState<
        AttributeDefinition | undefined
    >();
    const borderWidth = 3;
    const defaultThumbSize = 128;
    const [thumbsHeight, setThumbsHeight] = React.useState(
        defaultThumbSize + scrollbarWidth + borderWidth + 53
    );
    const [locale, setLocale] = React.useState<string>('en');
    const onSaved = React.useCallback(() => {
        toast.success(t('attribute_editor.saved', 'Saved!'));
        onClose();
    }, [t, onClose]);

    const {
        attributeDefinitions,
        values,
        definitionValues,
        toggleValue,
        hasValue,
        setValue,
        inputValueInc,
        history,
        undo,
        redo,
        onSave,
        createToKey,
        disabledAssets,
    } = useAttributeValues({
        attributeDefinitions: remoteAttributeDefinitions,
        assets,
        subSelection,
        setSubSelection,
        definition,
        setDefinition,
        onSaved,
    });

    useTabShortcut({
        attributeDefinitions,
        setDefinition,
    });

    const pages = React.useMemo(() => [assets], [assets]);

    useSelectAllKey(() => {
        setSubSelection(assets);
    }, [assets]);

    React.useEffect(() => {
        setSubSelection(assets);
    }, [assets]);

    const definitionLocale = definition?.translatable ? locale : NO_LOCALE;

    const actionsContext = React.useMemo<ActionsContext<Asset>>(() => {
        const removeFromSelectionLabel = t(
            'attribute_editor.remove_from_selection',
            `Remove from selection`
        );
        return {
            extraActions: [
                {
                    name: 'removeFromSelection',
                    labels: {
                        multi: removeFromSelectionLabel,
                        single: removeFromSelectionLabel,
                    },
                    icon: <DeleteIcon />,
                    color: 'warning',
                    buttonProps: {
                        variant: 'contained',
                    },
                    reload: true,
                    resetSelection: true,
                    apply: async items => {
                        removeFromSelection(items.map(i => i.id));
                    },
                },
            ],
        };
    }, [t]);

    const setAttributeValue = React.useCallback<SetAttributeValue>(
        (value, options) => {
            if (definition) {
                setValue(definitionLocale, value, options);
            }
        },
        [definition, setValue, definitionLocale]
    );

    const separatorBorderStyle = `${borderWidth}px solid ${theme.palette.divider}`;

    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                height: '100vh',
                overflow: 'hidden',
            }}
        >
            <AssetSelectionContext.Provider
                value={{
                    selection: subSelection!,
                    disabledAssets: disabledAssets ?? [],
                    setSelection: setSubSelection,
                }}
            >
                <Resizable
                    defaultSize={{
                        height: thumbsHeight + borderWidth,
                    }}
                    onResize={(_e, _direction, ref, _d) =>
                        setThumbsHeight((ref as HTMLDivElement)!.clientHeight)
                    }
                    minHeight={20}
                    enable={{
                        bottom: true,
                    }}
                    style={{
                        zIndex: 1,
                        borderBottom: separatorBorderStyle,
                    }}
                >
                    <div
                        style={{
                            width: '100%',
                            overflow: 'auto',
                            height: thumbsHeight,
                        }}
                    >
                        <DisplayProvider
                            inOverflowDiv={true}
                            displayPrefKey={'displayBatchEdit'}
                            defaultState={{
                                thumbSize: defaultThumbSize,
                                displayTitle: false,
                                displayPreview: true,
                                displayCollections: false,
                                displayTags: false,
                                displayAttributes: false,
                            }}
                        >
                            <AssetList
                                total={assets.length}
                                searchBar={false}
                                pages={pages}
                                defaultSelection={assets}
                                disabledAssets={disabledAssets}
                                subSelection={subSelection}
                                onSelectionChange={setSubSelection}
                                previewZIndex={ZIndex.modal + 1}
                                actionsContext={actionsContext}
                                itemOverlay={
                                    definition &&
                                    definition.multiple &&
                                    selectedValue
                                        ? ({item}: {item: Asset}) => {
                                              return (
                                                  <AssetToggleOverlay
                                                      onAdd={() =>
                                                          toggleValue(
                                                              item.id,
                                                              locale,
                                                              selectedValue!
                                                                  .value,
                                                              true
                                                          )
                                                      }
                                                      onRemove={() =>
                                                          toggleValue(
                                                              item.id,
                                                              locale,
                                                              selectedValue!
                                                                  .value,
                                                              false
                                                          )
                                                      }
                                                      checked={hasValue(
                                                          item,
                                                          locale,
                                                          selectedValue!.key
                                                      )}
                                                  />
                                              );
                                          }
                                        : undefined
                                }
                            />
                        </DisplayProvider>
                    </div>
                </Resizable>
                <div
                    style={{
                        flexGrow: 1,
                        flexShrink: 1,
                        height: `calc(100vh - ${thumbsHeight}px)`,
                        overflow: 'hidden',
                    }}
                >
                    <Box
                        sx={{
                            display: 'flex',
                            flexGrow: 1,
                            height: `calc(100vh - ${thumbsHeight}px)`,
                            strong: {
                                mr: 1,
                                verticalAlign: 'top',
                                alignSelf: 'start',
                            },
                        }}
                    >
                        <Resizable
                            defaultSize={{
                                width: 320,
                                height: 'auto',
                            }}
                            minWidth={30}
                            enable={{
                                right: true,
                            }}
                            style={{
                                borderRight: separatorBorderStyle,
                            }}
                        >
                            <div
                                style={{
                                    minHeight: '100%',
                                    maxHeight: '100%',
                                    overflow: 'auto',
                                }}
                            >
                                {attributeDefinitions ? (
                                    <Attributes
                                        attributeDefinitions={
                                            attributeDefinitions
                                        }
                                        definitionValues={definitionValues}
                                        setDefinition={setDefinition}
                                        definition={definition}
                                        locale={locale}
                                    />
                                ) : (
                                    <DefinitionsSkeleton />
                                )}
                            </div>
                        </Resizable>

                        <div
                            style={{
                                position: 'relative',
                                flexGrow: 1,
                                borderRight: separatorBorderStyle,
                                height: '100%',
                            }}
                        >
                            <div
                                style={{
                                    zIndex: theme.zIndex.fab,
                                    position: 'absolute',
                                    display: 'flex',
                                    bottom: theme.spacing(3),
                                    left: '50%',
                                    transform: 'translateX(-50%)',
                                    borderRadius: theme.shape.borderRadius,
                                }}
                            >
                                <AttributesToolbar
                                    undo={undo}
                                    redo={redo}
                                    hasChanges={history.current > 0}
                                    onSave={onSave}
                                    onClose={onClose}
                                />
                            </div>
                            <div
                                style={{
                                    height: '100%',
                                    overflow: 'auto',
                                    paddingBottom: 100,
                                }}
                            >
                                {values && definition ? (
                                    <EditorPanel
                                        selectedValue={selectedValue}
                                        setSelectedValue={setSelectedValue}
                                        inputValueInc={inputValueInc}
                                        definition={definition}
                                        valueContainer={values}
                                        subSelection={subSelection}
                                        setLocale={setLocale}
                                        locale={definitionLocale}
                                        setAttributeValue={setAttributeValue}
                                        createToKey={createToKey}
                                    />
                                ) : (
                                    ''
                                )}
                            </div>
                        </div>
                        <Resizable
                            defaultSize={{
                                width: defaultSuggestionsPanelWidth,
                                height: 'auto',
                            }}
                            enable={{
                                left: true,
                            }}
                            minWidth={30}
                            style={{
                                borderRight: separatorBorderStyle,
                            }}
                        >
                            <div
                                style={{
                                    minHeight: '100%',
                                    maxHeight: '100%',
                                    overflow: 'auto',
                                }}
                            >
                                <SuggestionPanel
                                    defaultPanelWidth={
                                        defaultSuggestionsPanelWidth
                                    }
                                    locale={definitionLocale}
                                    valueContainer={values}
                                    definition={definition}
                                    setAttributeValue={setAttributeValue}
                                    subSelection={subSelection}
                                    assets={assets}
                                    setSubSelection={setSubSelection}
                                    createToKey={createToKey}
                                />
                            </div>
                        </Resizable>
                    </Box>
                </div>
            </AssetSelectionContext.Provider>
        </div>
    );
}
