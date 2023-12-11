import Publication from '../component/Publication.jsx';
import {useParams} from '@alchemy/navigation';
import {useKeycloakUser as useUser} from '@alchemy/react-auth';

type Props = {};

export default function PublicationPage({}: Props) {
    const {id} = useParams();
    const {user} = useUser();

    return <Publication id={id} username={user?.username} />;
}
