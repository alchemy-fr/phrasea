'use client';

import {useTranslation} from 'react-i18next';
import {Badge} from '@/components/ui/misc';
import {cn} from '@/lib/utils/cn';
import {
    AnalysisMessage,
    analyzerResults,
    AnalyzerResult,
    FileAnalysis,
    logLevelLabels,
    resultLevel,
} from './analysis';

/** `duplicate_checksum {count: 1}` reads as `duplicate checksum (count: 1)` */
function messageText([, type, payload]: AnalysisMessage): string {
    const details = Object.entries(payload ?? {})
        .map(
            ([k, v]) =>
                `${k}: ${typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v)}`
        )
        .join(', ');
    const label = type.replace(/_/g, ' ');

    return details ? `${label} (${details})` : label;
}

function levelVariant(level: number) {
    if (level >= 3) {
        return 'destructive' as const;
    }
    if (level === 2) {
        return 'warning' as const;
    }

    return 'muted' as const;
}

type Row = {
    result: AnalyzerResult;
    /** First row of its analyzer: the one carrying the analyzer name */
    first: boolean;
    message?: AnalysisMessage;
};

function toRows(results: AnalyzerResult[]): Row[] {
    return results.flatMap((result): Row[] => {
        const messages = result.output?.messages ?? [];
        if (messages.length === 0) {
            return [{result, first: true}];
        }

        return messages.map((message, i) => ({
            result,
            first: i === 0,
            message,
        }));
    });
}

/**
 * Why the analyzers rejected the source file: one row per message, lined up in
 * three columns (analyzer, level, message).
 */
export function AnalysisReport({
    analysis,
    className,
}: {
    analysis: FileAnalysis;
    className?: string;
}) {
    const {t} = useTranslation();
    const results = analyzerResults(analysis);
    if (results.length === 0 && !analysis.message) {
        return null;
    }

    return (
        <div className={cn('text-xs', className)}>
            {analysis.message ? (
                <p className="mb-1">{analysis.message}</p>
            ) : null}
            <table className="w-full">
                <tbody className="align-baseline">
                    {toRows(results).map((row, i) => (
                        <tr key={i}>
                            <td className="py-0.5 pr-2 whitespace-nowrap">
                                {row.first ? (
                                    <Badge
                                        variant={levelVariant(
                                            resultLevel(row.result)
                                        )}
                                    >
                                        {row.result.name}
                                    </Badge>
                                ) : null}
                            </td>
                            <td className="py-0.5 pr-2 whitespace-nowrap text-muted-foreground uppercase">
                                {row.message
                                    ? (logLevelLabels[row.message[0]] ??
                                      String(row.message[0]))
                                    : null}
                            </td>
                            <td className="w-full py-0.5 break-words">
                                {row.message ? (
                                    messageText(row.message)
                                ) : (
                                    <span className="text-muted-foreground">
                                        {t(
                                            'quarantine.no_message',
                                            'Nothing to report'
                                        )}
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
