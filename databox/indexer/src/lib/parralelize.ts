
export async function parralelize<T>(
    getIterator: () => AsyncGenerator<T>,
    handler: (item: T) => Promise<void>,
    concurrency: number
): Promise<void> {
    const iterator = getIterator();

    const next = async () => {
        const f = await iterator.next();

        if (!f.done) {
            await handler(f.value);
            await next();
        }
    };

    const promises: Promise<void>[] = [];
    for (let i = 0; i < concurrency; ++i) {
        promises.push(next());
    }

    await Promise.all(promises);
}
