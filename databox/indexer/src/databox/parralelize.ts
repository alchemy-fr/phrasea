
export async function parralelize<T>(
    iterator: () => AsyncGenerator<T>,
    handler: (item: T) => Promise<void>,
    concurrency: number
): Promise<void> {
    let promises: Promise<void>[] = [];

    let i = 0;
    for await (const f of iterator()) {
        ++i;
        promises.push(handler(f));

        if (i >= concurrency && promises) {
            await Promise.all(promises);
            promises = [];
            i = 0;
        }
    }

    if (promises.length > 0) {
        await Promise.all(promises);
    }
}
