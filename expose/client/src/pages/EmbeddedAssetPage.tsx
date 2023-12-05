import {useParams} from '@alchemy/navigation';
import EmbeddedAsset from '../component/EmbeddedAsset.tsx';

type Props = {};

export default function EmbeddedAssetPage({}: Props) {
    const {id} = useParams();

    return <EmbeddedAsset id={id as string} />;
}
