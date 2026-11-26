import {appManifest} from '@alchemy/core/seo/manifest';
import {APP_DESCRIPTION, APP_FULL_NAME, APP_NAME} from '@/lib/seo/app';

export default function manifest() {
    return appManifest({
        name: APP_FULL_NAME,
        shortName: APP_NAME,
        description: APP_DESCRIPTION,
    });
}
