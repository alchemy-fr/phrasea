import {useParams} from '@alchemy/navigation';
import PublicationView from '../component/Publication/PublicationView.tsx';

type Props = {};

export default function AssetPage({}: Props) {
    const {id, assetId} = useParams();

    return <PublicationView id={id!} assetId={assetId} />;
}
