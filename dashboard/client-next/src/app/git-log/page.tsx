import type {Metadata} from 'next';
import {AppShell} from '@/components/layout/AppShell';
import {GitLogScreen} from '@/features/gitLog/GitLogScreen';
import {readGitLog} from '@/features/gitLog/gitLog';

export const metadata: Metadata = {title: 'Latest changes'};

export default async function GitLogPage() {
    return (
        <AppShell>
            <GitLogScreen commits={await readGitLog()} />
        </AppShell>
    );
}
