import {useParams} from '@alchemy/navigation';
import {PublicationProfile} from '../types.ts';
import {useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {getProfile} from '../api/profileApi.ts';
import ProfileEdit from '../components/publication/ProfileEdit.tsx';

type Props = {};

export default function ProfileEditPage({}: Props) {
    const {id} = useParams();
    const [data, setData] = useState<PublicationProfile>();

    useEffect(() => {
        (async () => {
            setData((await getProfile(id!)) as PublicationProfile);
        })();
    }, [id]);

    return (
        <>
            {data ? (
                <ProfileEdit data={data} />
            ) : (
                <FullPageLoader backdrop={false} />
            )}
        </>
    );
}
