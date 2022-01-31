
export function getEnvStrict(name: string): string
{
    const v = getEnv(name);
    if (!v) {
        console.error(`Missing env "${name}"`);
        process.exit(1);
    }

    return v;
}

export function getEnv(name: string, defaultValue?: string): string | undefined
{
    return process.env[name] || defaultValue;
}
