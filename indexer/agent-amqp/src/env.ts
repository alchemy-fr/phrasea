
export function getEnvStrict(name: string): string
{
    if (!process.env[name]) {
        console.error(`Missing env "${name}"`);
        process.exit(1);
    }

    return process.env[name];
}
