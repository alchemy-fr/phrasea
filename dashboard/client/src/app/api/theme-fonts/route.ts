import {getStackConfig} from '@alchemy/core/config/stackConfig';
import {themeFontsResponse} from '@alchemy/theme/compile';

/** The `@font-face` rules of the fonts uploaded with the organisation theme */
export const dynamic = 'force-dynamic';

export async function GET(): Promise<Response> {
    return themeFontsResponse(await getStackConfig());
}
