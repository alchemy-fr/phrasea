import {useParams} from '@alchemy/navigation';
import ShareView from '../components/Share/ShareView.tsx';

type Props = {};

export default function SharePage({}: Props) {
    const {id, token} = useParams() as {id: string; token: string};

    return <ShareView id={id} token={token} />;
}
