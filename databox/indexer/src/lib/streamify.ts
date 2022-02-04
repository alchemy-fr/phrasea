import {Readable} from "stream";

type Wrapper<T> = {
    value?: T;
    done: boolean;
}

export async function* streamify(stream: Readable, event: string, endEvent: string): AsyncGenerator<string, void> {
    let done = false;
    stream.on(endEvent, () => {
        done = true;
    });

    while (!done) {
        const r = await oncePromise(stream, event);
        if (r.done) {
            break;
        }

        yield r.value;
        stream.resume();
    }
}

function oncePromise(stream: Readable, event: string): Promise<Wrapper<string>> {
    return new Promise<Wrapper<string>>(resolve => {
        const handler = (obj: { name: string }) => {
            stream.pause();
            stream.removeListener(event, handler);

            resolve({
                value: obj.name,
                done: false,
            });
        };
        stream.addListener(event, handler);
    });
}
