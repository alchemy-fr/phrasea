import {useParams} from '@alchemy/navigation';
import Publication from '../component/Publication.jsx';
import {useAuth} from '@alchemy/react-auth';

type Props = {};

export default function AssetPage({}: Props) {
    const {id, assetId} = useParams();
    const {user} = useAuth();

    return <Publication id={id} assetId={assetId} username={user?.username} />;
}
