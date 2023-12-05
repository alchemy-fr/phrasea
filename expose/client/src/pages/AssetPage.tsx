import {useParams} from '@alchemy/navigation';
import Publication from '../component/Publication.jsx';
import {useKeycloakUser as useUser} from '@alchemy/auth';

type Props = {};

export default function AssetPage({}: Props) {
    const {id, assetId} = useParams();
    const {user} = useUser();

    return (
        <Publication
            id={id}
            assetId={assetId}
            username={user?.username}
        />
    );
}
