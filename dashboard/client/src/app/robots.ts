import {appRobots} from '@alchemy/core/seo/robots';
import {isIndexingAllowed} from '@alchemy/core/seo/indexing';
import {INDEXING_DEFAULT} from '@/lib/seo/app';

// From the container environment, never from the build
export const dynamic = 'force-dynamic';

export default function robots() {
    return appRobots({
        allowIndexing: isIndexingAllowed(process.env, INDEXING_DEFAULT),
    });
}
