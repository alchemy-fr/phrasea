import {Logger} from 'winston';

export type LoggedLine = {
    level: string;
    message: string;
};

export type TestLogger = Logger & {
    lines: LoggedLine[];
    messages(level?: string): string[];
    hasMessageMatching(pattern: RegExp | string): boolean;
};

const levels = ['debug', 'info', 'warn', 'error', 'verbose', 'silly'] as const;

/**
 * A stand-in for a winston Logger that writes nowhere but keeps every line, so
 * tests can assert on log output without polluting the reporter.
 *
 * The indexer only ever calls the level methods on the loggers it receives, so
 * a plain object is enough and keeps the tests free of winston internals.
 */
export function createTestLogger(): TestLogger {
    const lines: LoggedLine[] = [];

    const logger: Record<string, any> = {
        lines,
        messages: (level?: string) =>
            lines.filter(l => !level || l.level === level).map(l => l.message),
        hasMessageMatching: (pattern: RegExp | string) =>
            lines.some(l =>
                typeof pattern === 'string'
                    ? l.message.includes(pattern)
                    : pattern.test(l.message)
            ),
    };

    for (const level of levels) {
        logger[level] = (message: any) => {
            lines.push({level, message: String(message)});

            return logger;
        };
    }

    logger.log = (level: string, message: any) => {
        lines.push({level, message: String(message)});

        return logger;
    };

    return logger as TestLogger;
}
