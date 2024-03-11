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

type LogLevel = 'debug' | 'warn' | 'info' | 'error';

const loggerConfig: {
    level: LogLevel;
} = {
    level: 'info',
};

const loggers: Logger[] = [];

export function setLogLevel(level: LogLevel): void {
    loggerConfig.level = level;

    loggers.forEach(l => (l.level = level));
}

export function createLogger(context: string): Logger {
    const l = winstonCreateLogger({
        level: loggerConfig.level,
        format: combine(timestamp(), myFormat),
        defaultMeta: {context},
        transports: [new transports.Console()],
    });

    loggers.push(l);

    return l;
}
