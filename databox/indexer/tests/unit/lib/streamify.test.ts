import {Readable} from 'stream';
import {streamify} from '../../../src/lib/streamify';

/**
 * streamify() listens for `event` on the stream and reads `obj.name` off the
 * emitted payload; the stream itself is only used as an event emitter with
 * pause/resume, so a bare Readable is enough.
 */
function emitter(): Readable {
    return new Readable({read() {}});
}

const tick = () => new Promise(r => setImmediate(r));

function collect(stream: Readable): Promise<string[]> {
    return (async () => {
        const out: string[] = [];
        for await (const name of streamify(stream, 'data', 'end')) {
            out.push(name);
        }

        return out;
    })();
}

describe('streamify', () => {
    it('yields the `name` of every emitted object', async () => {
        const stream = emitter();
        const collected = collect(stream);

        for (const name of ['a', 'b', 'c']) {
            await tick();
            stream.emit('data', {name});
        }

        await tick();
        stream.emit('end');

        await expect(collected).resolves.toEqual(['a', 'b', 'c']);
    });

    it('terminates on the end event alone', async () => {
        // The generator waits on the value, the end and the error at once: a
        // stream that ends normally has to finish it, with no further event.
        const stream = emitter();
        const collected = collect(stream);

        await tick();
        stream.emit('data', {name: 'a'});
        await tick();
        stream.emit('end');

        const settled = await Promise.race([
            collected.then(() => 'completed'),
            new Promise(r => setTimeout(() => r('still-pending'), 50)),
        ]);

        expect(settled).toEqual('completed');
        await expect(collected).resolves.toEqual(['a']);
    });

    it('terminates on a stream that had already ended', async () => {
        // Nothing is left to listen for here, so the generator has to notice
        // on its own rather than wait for an event that will never come.
        const stream = new Readable({
            read() {
                this.push(null);
            },
        });
        stream.resume();
        await tick();

        expect(stream.readableEnded).toBe(true);
        await expect(collect(stream)).resolves.toEqual([]);
    });

    it('rejects when the stream errors', async () => {
        const stream = emitter();
        const collected = collect(stream);

        await tick();
        stream.emit('error', new Error('boom'));

        await expect(collected).rejects.toThrow('boom');
    });

    it('pauses the stream between items and resumes after each yield', async () => {
        const stream = emitter();
        const pause = vi.spyOn(stream, 'pause');
        const resume = vi.spyOn(stream, 'resume');

        const collected = collect(stream);

        await tick();
        stream.emit('data', {name: 'only'});
        await tick();
        stream.emit('end');
        await collected;

        expect(pause).toHaveBeenCalled();
        expect(resume).toHaveBeenCalled();
    });

    it('removes its listeners after each item, so no handler leaks', async () => {
        const stream = emitter();
        const collected = collect(stream);

        await tick();
        stream.emit('data', {name: 'a'});
        await tick();

        expect(stream.listenerCount('data')).toEqual(1);

        stream.emit('end');
        await collected;

        expect(stream.listenerCount('data')).toEqual(0);
        // The persistent end listener the generator installs to notice a
        // stream that was already over stays until the stream is dropped.
        expect(stream.listenerCount('error')).toEqual(0);
    });
});
