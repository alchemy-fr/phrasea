import React from 'react';
import {ResultContext} from '../../../Media/Search/ResultContext.tsx';
import DebugEsModal from '../../../Media/Search/DebugEsModal.tsx';
import {useOpenAsset} from '../../../AssetSearch/useOpenAsset.ts';
import AssetList from '../../../AssetList/AssetList.tsx';
import NoSearchResult from '../../../AssetSearch/NoSearchResult.tsx';
import {useModals} from '@alchemy/navigation';
import {Box, Paper} from '@mui/material';
import FacetsProxy from '../../../Media/Asset/Facets/Facets.tsx';
import {ActionsContext} from '../../../AssetList/types.ts';
import {Asset} from '../../../../types.ts';

type Props = {
    displayFacets: boolean;
    containerHeight: number;
    actions: ActionsContext<Asset>;
};

export default function SearchGrid({
    displayFacets,
    containerHeight,
    actions,
}: Props) {
    const resultContext = React.useContext(ResultContext);
    const {openModal} = useModals();

    const openDebug = resultContext.debug
        ? () => {
              openModal(DebugEsModal, {
                  debug: resultContext.debug!,
              });
          }
        : undefined;

    const onOpen = useOpenAsset({
        resultContext,
    });

    return (
        <>
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'row',
                    gap: 1,
                }}
            >
                {displayFacets && (
                    <Paper
                        style={{
                            width: 250,
                            height: containerHeight,
                            overflow: 'auto',
                        }}
                    >
                        <FacetsProxy />
                    </Paper>
                )}
                <div
                    style={{
                        flex: 1,
                        height: containerHeight,
                    }}
                >
                    <AssetList
                        pages={resultContext!.pages}
                        reload={resultContext!.reload}
                        total={resultContext!.total}
                        loading={resultContext!.loading}
                        loadMore={resultContext.loadMore}
                        onOpenDebug={openDebug}
                        onOpen={onOpen}
                        actionsContext={{
                            ...actions,
                        }}
                        noResultsMessage={<NoSearchResult />}
                    />
                </div>
            </Box>
        </>
    );
}
