export async function promiseConcurrency<T>(promises: (() => Promise<T>)[], concurrency: number = 2): Promise<T[]> {
    const allPromises = [...promises];

    const results: T[] = [];

    return new Promise<T[]>((resolve, reject) => {
        const next = () => {
            const p = allPromises.shift();

            if (p) {
                p()
                    .then(r => {
                        results.push(r);
                        next()
                    })
                    .catch(e => reject(e));
            } else {
                resolve(results);
            }
        }

        for (let i = 0; i < concurrency; i++) {
            next();
        }
    });
}
