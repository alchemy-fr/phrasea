import {Suspense} from 'react';
import {QuarantineScreen} from '@/features/assets/quarantine/QuarantineScreen';
import {FullPageLoader} from '@/components/ui/loader';

export default function QuarantinePage() {
    return (
        <Suspense fallback={<FullPageLoader />}>
            <QuarantineScreen />
        </Suspense>
    );
}
