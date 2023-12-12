import {
    createLogger as winstonCreateLogger,
    transports,
    Logger,
    format,
} from 'winston';
const {combine, timestamp, printf} = format;

const myFormat = printf(({context, level, message, timestamp}) => {
    return `${timestamp} ${context}.${level.toUpperCase()}: ${message}`;
});

export function createLogger(context: string): Logger {
    return winstonCreateLogger({
        level: 'debug',
        format: combine(timestamp(), myFormat),
        defaultMeta: {context},
        transports: [new transports.Console()],
    });
}
