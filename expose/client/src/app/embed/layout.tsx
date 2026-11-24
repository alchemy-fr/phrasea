import type {Metadata} from 'next';
import {NO_INDEX} from '@alchemy/next-core/seo/metadata';

/** The players embedded in other sites: indexed through their publication */
export const metadata: Metadata = {robots: NO_INDEX};

export default function EmbedLayout({children}: {children: React.ReactNode}) {
    return children;
}
