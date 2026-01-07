import {useParams} from '@alchemy/navigation';
import PublicationView from '../components/publication/PublicationView.tsx';

type Props = {};

export default function PublicationPage({}: Props) {
    const {id, assetId} = useParams();

    return <PublicationView id={id!} assetId={assetId} />;
}
