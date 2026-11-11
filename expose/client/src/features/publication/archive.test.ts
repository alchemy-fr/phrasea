import {describe, expect, it, vi} from 'vitest';
import type {ArchiveStatus} from '@/lib/api/publications';
import {getArchiveRetryDelay, waitForArchive} from './archive';

function fakeClock() {
    let time = 0;

    return {
        now: () => time,
        sleep: vi.fn(async (ms: number) => {
            time += ms;
        }),
    };
}

describe('waitForArchive', () => {
    it('backs off from 2 to 15 seconds', () => {
        expect([0, 1, 2, 3, 4, 5, 10].map(getArchiveRetryDelay)).toEqual([
            2000, 3000, 4500, 6750, 10125, 15000, 15000,
        ]);
    });

    it('checks again until the archive is ready', async () => {
        const clock = fakeClock();
        const statuses: ArchiveStatus[] = [
            {status: 'preparing'},
            {status: 'preparing'},
            {status: 'ready', url: 'https://s3/archive.zip'},
        ];
        const check = vi.fn(async () => statuses.shift()!);

        await expect(waitForArchive(check, clock)).resolves.toEqual({
            status: 'ready',
            url: 'https://s3/archive.zip',
        });
        expect(check).toHaveBeenCalledTimes(3);
        expect(clock.sleep.mock.calls.map(([ms]) => ms)).toEqual([2000, 3000]);
    });

    it('gives up after the maximum wait', async () => {
        const clock = fakeClock();
        const check = vi.fn(async (): Promise<ArchiveStatus> => ({
            status: 'preparing',
        }));

        await expect(
            waitForArchive(check, {...clock, maxWait: 30_000})
        ).resolves.toEqual({status: 'timeout'});
        expect(clock.now()).toBeLessThanOrEqual(30_000);
        expect(check).toHaveBeenCalledTimes(6);
    });

    it('stops when aborted', async () => {
        const controller = new AbortController();
        const check = vi.fn(async (): Promise<ArchiveStatus> => {
            controller.abort(new Error('closed'));

            return {status: 'preparing'};
        });

        await expect(
            waitForArchive(check, {signal: controller.signal})
        ).rejects.toThrow('closed');
        expect(check).toHaveBeenCalledTimes(1);
    });
});
