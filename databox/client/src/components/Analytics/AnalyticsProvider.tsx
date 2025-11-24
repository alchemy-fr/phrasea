import {PropsWithChildren} from 'react';
import {matomo} from '../../lib/matomo';
import {MatomoProvider} from '@jonkoops/matomo-tracker-react';

type Props = PropsWithChildren<{}>;

export default function AnalyticsProvider({children}: Props) {
    if (matomo) {
        return <MatomoProvider value={matomo}>{children}</MatomoProvider>;
    }

    return children as JSX.Element;
}
