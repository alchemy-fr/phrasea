import {useContext} from "react";
import {ResultContext} from "../Media/Search/ResultContext.tsx";
import AssetList from "../AssetList/AssetList.tsx";
import DebugEsModal from "../Media/Search/DebugEsModal.tsx";
import {useModals} from '@alchemy/navigation';

type Props = {};

export default function AssetSearch({}: Props) {
    const resultContext = useContext(ResultContext);
    const {openModal} = useModals();

    const openDebug = resultContext.debug
        ? () => {
            openModal(DebugEsModal, {
                debug: resultContext.debug!,
            });
        }
        : undefined;

    return <>
        <AssetList
            pages={resultContext!.pages}
            reload={resultContext!.reload}
            total={resultContext!.total}
            loading={resultContext!.loading}
            loadMore={resultContext.loadMore}
            onOpenDebug={openDebug}
        />
    </>
}
