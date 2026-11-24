import {getServerConfig} from '@/lib/config/server';
import {buildPhraseaManifest} from '@/lib/phraseaManifest';

export const dynamic = 'force-dynamic';

export function GET(): Response {
    return Response.json(buildPhraseaManifest(getServerConfig()));
}
