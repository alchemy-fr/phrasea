import {Readable} from 'stream';

type Wrapper<T> = {
    value?: T;
    done: boolean;
};

export async function* streamify(
    stream: Readable,
    event: string,
    endEvent: string
): AsyncGenerator<string, void> {
    // The stream is paused between two values, and a paused stream emits
    // neither `event` nor `endEvent`. The flag and the `readableEnded` check
    // below only cover a stream that was already over when the generator was
    // first pulled — there is no event left to wait for in that case.
    let ended = false;
    stream.on(endEvent, () => {
        ended = true;
    });

    for (;;) {
        if (ended || stream.readableEnded) {
            return;
        }

        const r = await nextValue(stream, event, endEvent);
        if (r.done) {
            return;
        }

        yield r.value!;
        stream.resume();
    }
}

/**
 * Resolves on the next value, on the end of the stream, or rejects on its
 * error. All three have to be waited on at once: waiting for a value alone
 * leaves the generator pending forever once the stream is over.
 */
function nextValue(
    stream: Readable,
    event: string,
    endEvent: string
): Promise<Wrapper<string>> {
    return new Promise<Wrapper<string>>((resolve, reject) => {
        const cleanup = () => {
            stream.removeListener(event, onValue);
            stream.removeListener(endEvent, onEnd);
            stream.removeListener('error', onError);
        };

        const onValue = (obj: {name: string}) => {
            stream.pause();
            cleanup();
            resolve({
                value: obj.name,
                done: false,
            });
        };

        const onEnd = () => {
            cleanup();
            resolve({done: true});
        };

        const onError = (err: Error) => {
            cleanup();
            reject(err);
        };

        stream.addListener(event, onValue);
        stream.addListener(endEvent, onEnd);
        stream.addListener('error', onError);
    });
}
