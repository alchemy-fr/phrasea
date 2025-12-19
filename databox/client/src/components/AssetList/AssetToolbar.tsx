import {Box} from '@mui/material';
import {ZIndex} from '../../themes/zIndex';
import SearchBar from '../Media/Search/SearchBar';
import SelectionActions, {
    SelectionActionsProps,
} from './Toolbar/SelectionActions';
import {AssetOrAssetContainer} from '../../types';
import assetClasses from './classes';
import TotalResults from './Toolbar/TotalResults.tsx';
import React, {Context, useContext} from 'react';
import DisplaySettingButton from './Toolbar/DisplaySettingButton.tsx';
import {TSelectionContext} from '../../context/AssetSelectionContext.tsx';
import AnimatedLoader from './AnimatedLoader.tsx';

type SelectionContextDefinition<Item extends AssetOrAssetContainer> = Context<
    TSelectionContext<Item>
>;

type Props<Item extends AssetOrAssetContainer> = {
    searchBar?: boolean;
    selectionContextDefinition: SelectionContextDefinition<Item>;
} & Omit<SelectionActionsProps<Item>, 'selectionContext'>;

export default function AssetToolbar<Item extends AssetOrAssetContainer>({
    searchBar = true,
    selectionContextDefinition,
    ...selectionActionsProps
}: Props<Item>) {
    const selectionContext = useContext(selectionContextDefinition);

    return (
        <>
            <Box
                className={assetClasses.assetToolbar}
                component="div"
                sx={() => ({
                    position: 'sticky',
                    top: 0,
                    zIndex: ZIndex.toolbar,
                    bgcolor: 'background.default',
                })}
            >
                {searchBar ? <SearchBar /> : ''}

                <Box
                    sx={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 1,
                        mx: 2,
                    }}
                >
                    <div
                        style={{
                            flexGrow: 1,
                        }}
                    >
                        <SelectionActions
                            selectionContext={selectionContext}
                            {...selectionActionsProps}
                        />
                    </div>
                    <div>
                        <TotalResults
                            selectionContext={selectionContext}
                            {...selectionActionsProps}
                        />
                    </div>
                    <div>
                        <DisplaySettingButton />
                    </div>
                </Box>
            </Box>
            <div
                style={{
                    position: 'absolute',
                    left: '0',
                    right: '0',
                    zIndex: 100,
                    textAlign: 'center',
                }}
            >
                <AnimatedLoader loading={selectionActionsProps.loading} />
            </div>
        </>
    );
}
