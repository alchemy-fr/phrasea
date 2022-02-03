import fs from "fs";
import path from "path";

export const scanRecursiveDir = async (dir: string): Promise<string[]> => {
    return new Promise((resolve, reject) => {
        let results: string[] = [];
        fs.readdir(dir, function (err, list) {
            if (err) {
                return reject(err);
            }
            let pending = list.length;
            if (!pending) {
                return resolve(results);
            }

            list.forEach((file) => {
                file = path.resolve(dir, file);
                fs.stat(file, async (err, stat) => {
                    if (stat && stat.isDirectory()) {
                        try {
                            results = results.concat(await scanRecursiveDir(file));
                            if (!--pending) {
                                resolve(results);
                            }
                        } catch (e) {
                            reject(e);
                        }
                    } else {
                        results.push(file);
                        if (!--pending) {
                            resolve(results);
                        }
                    }
                });
            });
        });
    });
};
