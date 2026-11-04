import {redirect} from 'next/navigation';
import {routes} from '@/lib/routes';

/** The client opens on the assets */
export default function IndexPage() {
    redirect(routes.assets());
}
