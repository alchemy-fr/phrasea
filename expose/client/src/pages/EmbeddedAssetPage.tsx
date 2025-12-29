import {useParams} from '@alchemy/navigation';
import EmbeddedAsset from '../component/Publication/Asset/EmbeddedAsset.tsx';

type Props = {};

export default function EmbeddedAssetPage({}: Props) {
    const {assetId} = useParams();

    return <EmbeddedAsset id={assetId!} />;
}
