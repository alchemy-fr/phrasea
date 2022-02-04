
export async function parralelize<T>(
    iterator: () => AsyncGenerator<T>,
    handler: (item: T) => Promise<void>,
    concurrency: number
): Promise<void> {
    for await (const f of iterator()) {
        const promises = [];
        for (let i = 0; i < concurrency; ++i) {
            promises.push(handler(f));
        }

        await Promise.all(promises);
    }
}
