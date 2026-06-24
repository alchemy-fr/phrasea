import axios, {AxiosError} from 'axios';

process.on('uncaughtException', (err, origin) => {
    // eslint-disable-next-line no-console
    console.error('Uncaught exception:', err.message);

    // eslint-disable-next-line no-console
    console.debug(formatAxiosError(err));

    process.exit(1);
});

function formatAxiosError(error: any) {
    if (!axios.isAxiosError(error)) {
        return {
            message: error?.message,
            stack: error?.stack,
        };
    }

    return {
        message: error.message,
        code: error.code,
        method: error.config?.method?.toUpperCase(),
        url: error.config?.url,
        status: error.response?.status,
        statusText: error.response?.statusText,
        response: error.response?.data,
    };
}
