import {routes} from '../routes.ts';
import {getPath, useNavigate} from '@alchemy/navigation';
import {useEffect} from 'react';

type Props = {};

export default function HomePage({}: Props) {
    const navigate = useNavigate();

    useEffect(() => {
        navigate(getPath(routes.pageEdit));
    }, [navigate]);

    return null;
}
