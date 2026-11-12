'use client';

import {AuthCallbackPage} from '@alchemy/next-core/auth/AuthCallbackPage';
import {getAuthClient} from '@/lib/auth/client';

export default function Page() {
    return <AuthCallbackPage getClient={getAuthClient} />;
}
