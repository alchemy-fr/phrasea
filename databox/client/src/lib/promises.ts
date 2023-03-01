export async function promiseConcurrency<T>(promises: (() => Promise<T>)[], concurrency: number = 2): Promise<T[]> {
    const allPromises = [...promises];

    const results: T[] = [];

    return new Promise<T[]>((resolve, reject) => {
        let stack = 0;
        const next = () => {
            const p = allPromises.shift();

            ++stack;
            p!()
                .then(r => {
                    results.push(r);
                    --stack;

                    if (allPromises.length > 0) {
                        next();
                    } else {
                        if (stack === 0) {
                            resolve(results);
                        }
                    }
                })
                .catch(e => reject(e));
        }

        for (let i = 0; i < concurrency && allPromises.length > 0; i++) {
            next();
        }
    });
}
