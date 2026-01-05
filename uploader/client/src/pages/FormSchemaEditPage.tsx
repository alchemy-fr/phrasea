import {useParams} from '@alchemy/navigation';
import FormSchemaEdit from '../components/FormEditor/FormSchemaEdit.tsx';

type Props = {};

export default function FormSchemaEditPage({}: Props) {
    const {id} = useParams();

    return (
        <>
            <FormSchemaEdit formId={id} />
        </>
    );
}
