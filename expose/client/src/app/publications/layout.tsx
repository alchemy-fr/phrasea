import type {Metadata} from 'next';
import {NO_INDEX} from '@alchemy/next-core/seo/metadata';

/** The administration of the publications: not for the search engines */
export const metadata: Metadata = {robots: NO_INDEX};

export default function PublicationsLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return children;
}
