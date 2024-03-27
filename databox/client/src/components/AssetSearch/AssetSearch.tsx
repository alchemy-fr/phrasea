import React, {MouseEventHandler, useCallback} from 'react';
import {ResultContext} from '../Media/Search/ResultContext.tsx';
import AssetList from '../AssetList/AssetList.tsx';
import DebugEsModal from '../Media/Search/DebugEsModal.tsx';
import {useModals} from '@alchemy/navigation';
import {Fab} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import {useAuth} from '@alchemy/react-auth';
import UploadModal from '../Upload/UploadModal.tsx';
import {modalRoutes} from '../../routes.ts';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {OnOpen} from '../AssetList/types.ts';

type Props = {};

export default function AssetSearch({}: Props) {
    const resultContext = React.useContext(ResultContext);
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();
    const authContext = useAuth();

    const openDebug = resultContext.debug
        ? () => {
              openModal(DebugEsModal, {
                  debug: resultContext.debug!,
              });
          }
        : undefined;

    const openUpload = useCallback<
        MouseEventHandler<HTMLButtonElement>
    >((): void => {
        openModal(UploadModal, {
            files: [],
            userId: authContext.user!.id,
        });
    }, [authContext]);

    const onOpen = useCallback<OnOpen>(
        (asset, renditionId): void => {
            navigateToModal(modalRoutes.assets.routes.view, {
                id: asset.id,
                renditionId,
            });
            // eslint-disable-next-line
        },
        [navigateToModal]
    );

    return (
        <>
            <AssetList
                pages={resultContext!.pages}
                reload={resultContext!.reload}
                total={resultContext!.total}
                loading={resultContext!.loading}
                loadMore={resultContext.loadMore}
                onOpenDebug={openDebug}
                onOpen={onOpen}
            />
            {authContext.isAuthenticated() && (
                <Fab
                    onClick={openUpload}
                    color="primary"
                    aria-label="add"
                    sx={theme => ({
                        position: 'absolute',
                        bottom: theme.spacing(2),
                        right: theme.spacing(2),
                    })}
                >
                    <AddIcon />
                </Fab>
            )}
        </>
    );
}
