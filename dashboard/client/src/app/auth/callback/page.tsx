'use client';

import {AuthCallbackPage} from '@alchemy/core/auth/AuthCallbackPage';
import {getAuthClient} from '@/lib/auth/client';

export default function Page() {
    return <AuthCallbackPage getClient={getAuthClient} />;
}
