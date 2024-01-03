import Publication from '../component/Publication.jsx';
import {useParams} from '@alchemy/navigation';
import {useAuth} from '@alchemy/react-auth';

type Props = {};

export default function PublicationPage({}: Props) {
    const {id} = useParams();
    const {user} = useAuth();

    return <Publication id={id} username={user?.username} />;
}
