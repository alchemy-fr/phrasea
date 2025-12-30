import {useParams} from '@alchemy/navigation';
import PublicationView from '../components/Publication/PublicationView.tsx';

type Props = {};

export default function PublicationPage({}: Props) {
    const {id, assetId} = useParams();

    return <PublicationView id={id!} assetId={assetId} />;
}
