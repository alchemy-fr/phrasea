import React from 'react';
import {ResultContext} from '../../../Media/Search/ResultContext.tsx';
import DebugEsModal from '../../../Media/Search/DebugEsModal.tsx';
import {useOpenAsset} from '../../../AssetSearch/useOpenAsset.ts';
import AssetList from '../../../AssetList/AssetList.tsx';
import NoSearchResult from '../../../AssetSearch/NoSearchResult.tsx';
import {useModals} from '@alchemy/navigation';
import {Box, Paper} from '@mui/material';
import FacetsProxy from '../../../Media/Asset/Facets.tsx';

type Props = {};

export default function SearchGrid({}: Props) {
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
                <Paper
                    style={{
                        width: 250,
                    }}
                >
                    <FacetsProxy />
                </Paper>
                <div
                    style={{
                        flex: 1,
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
                        noResultsMessage={<NoSearchResult />}
                    />
                </div>
            </Box>
        </>
    );
}
