import {useAppInit} from './hooks/useAppInit';
import {UseAppInitProps} from './types';

export default function UserHookCaller(props: UseAppInitProps) {
    useAppInit(props);

    return null;
}
