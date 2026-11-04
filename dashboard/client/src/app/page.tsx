import {AppShell} from '@/components/layout/AppShell';
import {DashboardScreen} from '@/features/dashboard/DashboardScreen';

export default function HomePage() {
    return (
        <AppShell>
            <DashboardScreen />
        </AppShell>
    );
}
