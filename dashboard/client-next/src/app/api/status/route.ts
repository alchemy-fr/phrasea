import {getServiceStatuses} from '@/features/status/health';

/** The status of each API of the stack, polled by the service cards */
export const dynamic = 'force-dynamic';

export async function GET(): Promise<Response> {
    return Response.json(await getServiceStatuses(), {
        headers: {'Cache-Control': 'no-store'},
    });
}
