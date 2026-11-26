import type {Metadata} from 'next';
import {NO_INDEX} from '@alchemy/core/seo/metadata';

/** The steps of the sign-in: not for the search engines */
export const metadata: Metadata = {robots: NO_INDEX};

export default function AuthLayout({children}: {children: React.ReactNode}) {
    return children;
}
