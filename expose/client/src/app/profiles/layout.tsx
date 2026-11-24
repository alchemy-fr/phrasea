import type {Metadata} from 'next';
import {NO_INDEX} from '@alchemy/next-core/seo/metadata';

/** The administration of the profiles: not for the search engines */
export const metadata: Metadata = {robots: NO_INDEX};

export default function ProfilesLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return children;
}
