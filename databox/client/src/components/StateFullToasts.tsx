import {usePendingUploads} from '../hooks/usePendingUploads.tsx';
import {usePendingExports} from '../hooks/usePendingExports.tsx';

type Props = {};

export default function StateFullToasts({}: Props) {
    usePendingUploads();
    usePendingExports();

    return null;
}
