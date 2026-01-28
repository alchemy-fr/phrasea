import {useParams} from '@alchemy/navigation';
import TargetParamEdit from '../components/TargetParam/TargetParamEdit.tsx';

type Props = {};

export default function TargetParamEditPage({}: Props) {
    const {id} = useParams();

    return (
        <>
            <TargetParamEdit id={id} />
        </>
    );
}
