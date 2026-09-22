import {getStackConfig} from '@/lib/config/stackConfig';
import {compileStackTheme} from '@/features/theme/compile';

/**
 * The `@font-face` rules of the fonts uploaded with the organisation theme.
 * The page links it with the digest of its content, so the browser keeps it
 * for good and fetches it again the day an administrator changes the fonts.
 */
export const dynamic = 'force-dynamic';

export async function GET(): Promise<Response> {
    const css = compileStackTheme(await getStackConfig())?.fontsCss ?? '';

    return new Response(css, {
        headers: {
            'Content-Type': 'text/css; charset=utf-8',
            'Cache-Control': css
                ? 'public, max-age=31536000, immutable'
                : 'no-store',
        },
    });
}
