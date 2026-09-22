import {parralelize} from '../../../src/lib/parralelize';

async function* range(n: number): AsyncGenerator<number> {
    for (let i = 0; i < n; ++i) {
        yield i;
    }
}

const tick = () => new Promise(resolve => setImmediate(resolve));

describe('parralelize', () => {
    it('consumes the whole iterator exactly once', async () => {
        const seen: number[] = [];

        await parralelize(
            () => range(5),
            async i => {
                seen.push(i);
            },
            2
        );

        expect(seen.sort((a, b) => a - b)).toEqual([0, 1, 2, 3, 4]);
    });

    it('never runs more handlers at once than the requested concurrency', async () => {
        let running = 0;
        let maxRunning = 0;

        await parralelize(
            () => range(20),
            async () => {
                running++;
                maxRunning = Math.max(maxRunning, running);
                await tick();
                running--;
            },
            3
        );

        expect(maxRunning).toEqual(3);
    });

    it('actually parallelises: with concurrency 1 only one handler runs at a time', async () => {
        let running = 0;
        let maxRunning = 0;

        await parralelize(
            () => range(5),
            async () => {
                running++;
                maxRunning = Math.max(maxRunning, running);
                await tick();
                running--;
            },
            1
        );

        expect(maxRunning).toEqual(1);
    });

    it('handles an empty iterator', async () => {
        const handler = vi.fn();

        await parralelize(() => range(0), handler, 4);

        expect(handler).not.toHaveBeenCalled();
    });

    it('calls getIterator once, not once per worker', async () => {
        const getIterator = vi.fn(() => range(3));

        await parralelize(getIterator, async () => {}, 3);

        expect(getIterator).toHaveBeenCalledTimes(1);
    });

    it('propagates a handler error', async () => {
        await expect(
            parralelize(
                () => range(3),
                async i => {
                    if (i === 1) {
                        throw new Error('boom');
                    }
                },
                1
            )
        ).rejects.toThrow('boom');
    });
});
