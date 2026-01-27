import {useParams} from '@alchemy/navigation';
import PublicationEdit from '../components/publication/PublicationEdit.tsx';
import {Publication} from '../types.ts';
import {useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {loadPublication} from '../api/publicationApi.ts';

type Props = {};

export default function PublicationEditPage({}: Props) {
    const {id} = useParams();
    const [data, setData] = useState<Publication>();

    useEffect(() => {
        (async () => {
            setData((await loadPublication(id!)) as Publication);
        })();
    }, [id]);

    return (
        <>
            {data ? (
                <PublicationEdit data={data} />
            ) : (
                <FullPageLoader backdrop={false} />
            )}
        </>
    );
}
