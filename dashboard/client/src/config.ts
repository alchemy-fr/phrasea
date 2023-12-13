
declare global {
    interface Window {
        config: {
            locales: string[];
            env: Record<string, string>;
        };
    }
}

const config = window.config;

export default config;
