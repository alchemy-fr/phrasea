import {Suspense} from 'react';
import {FacesScreen} from '@/features/faces/FacesScreen';
import {FullPageLoader} from '@/components/ui/loader';

export default function FacesPage() {
    return (
        <Suspense fallback={<FullPageLoader />}>
            <FacesScreen />
        </Suspense>
    );
}
