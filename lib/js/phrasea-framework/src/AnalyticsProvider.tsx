import {MatomoProvider} from '@jonkoops/matomo-tracker-react';
import {MatomoInstance} from '@jonkoops/matomo-tracker-react/src/types.ts';
import {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{
    matomo: MatomoInstance | undefined;
}>;

export default function AnalyticsProvider({matomo, children}: Props) {
    if (matomo) {
        return <MatomoProvider value={matomo}>{children}</MatomoProvider>;
    }

    return children;
}
