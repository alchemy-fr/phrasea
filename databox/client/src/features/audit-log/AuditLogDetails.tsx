'use client';

import {Fragment, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import type {AuditLog, AuditLogAttributeChange} from '@/types/api';

type FieldChange =
    [unknown, unknown] | {added?: unknown[]; removed?: unknown[]};

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '∅';
    }
    if (typeof value === 'string') {
        return value;
    }

    return JSON.stringify(value);
}

function formatValues(values: unknown[]): string {
    return values.length ? values.map(formatValue).join(', ') : '∅';
}

function ChangeTable({
    rows,
}: {
    rows: {key: string; label: string; old: string; new: string}[];
}) {
    const {t} = useTranslation();

    return (
        <table className="w-full text-xs" data-testid="audit-log-changes">
            <thead className="text-left text-muted-foreground">
                <tr>
                    <th className="py-1 pr-3 font-medium">
                        {t('audit_log.details.field', 'Field')}
                    </th>
                    <th className="py-1 pr-3 font-medium">
                        {t('audit_log.details.old', 'Before')}
                    </th>
                    <th className="py-1 font-medium">
                        {t('audit_log.details.new', 'After')}
                    </th>
                </tr>
            </thead>
            <tbody>
                {rows.map(r => (
                    <tr key={r.key} className="border-t align-top">
                        <td className="py-1 pr-3 font-medium">{r.label}</td>
                        <td className="py-1 pr-3 break-all text-muted-foreground line-through decoration-muted-foreground/40">
                            {r.old}
                        </td>
                        <td className="py-1 break-all">{r.new}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function attributeRows(changes: AuditLogAttributeChange[]) {
    return changes.map((c, i) => ({
        key: `${c.definitionId}-${c.locale ?? ''}-${i}`,
        label: [c.definitionName ?? c.definitionId, c.locale]
            .filter(Boolean)
            .join(' · '),
        old: formatValues(c.old ?? []),
        new: formatValues(c.new ?? []),
    }));
}

function fieldRows(changes: Record<string, FieldChange>) {
    return Object.entries(changes).map(([field, change]) => {
        if (Array.isArray(change)) {
            return {
                key: field,
                label: field,
                old: formatValue(change[0]),
                new: formatValue(change[1]),
            };
        }

        return {
            key: field,
            label: field,
            old: change.removed?.length
                ? `− ${formatValues(change.removed)}`
                : '',
            new: change.added?.length ? `+ ${formatValues(change.added)}` : '',
        };
    });
}

/**
 * The details of an entry: the changes before → after when there are, the raw data otherwise.
 */
export function AuditLogDetails({log}: {log: AuditLog}) {
    const {t} = useTranslation();
    const data = (Array.isArray(log.data) ? {} : log.data) as Record<
        string,
        unknown
    >;

    let table: ReactNode = null;
    if (
        log.action === 'asset_attribute_changed' &&
        Array.isArray(data.changes)
    ) {
        table = (
            <ChangeTable
                rows={attributeRows(data.changes as AuditLogAttributeChange[])}
            />
        );
    } else if (
        log.action === 'object_updated' &&
        data.changes &&
        typeof data.changes === 'object' &&
        !Array.isArray(data.changes)
    ) {
        table = (
            <ChangeTable
                rows={fieldRows(data.changes as Record<string, FieldChange>)}
            />
        );
    }

    const rest = table
        ? Object.fromEntries(
              Object.entries(data).filter(([k]) => k !== 'changes')
          )
        : data;

    return (
        <div
            data-testid="audit-log-details"
            className="space-y-2 border-t bg-muted/30 px-3 py-2"
        >
            {table}
            {Object.keys(rest).length ? (
                <pre className="overflow-x-auto text-xs whitespace-pre-wrap break-all">
                    {JSON.stringify(rest, null, 2)}
                </pre>
            ) : null}
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                {log.objectId ? (
                    <>
                        <dt>{t('audit_log.details.object_id', 'Object ID')}</dt>
                        <dd className="font-mono break-all">{log.objectId}</dd>
                    </>
                ) : null}
                {log.ip ? (
                    <>
                        <dt>{t('audit_log.details.ip', 'IP')}</dt>
                        <dd className="font-mono">{log.ip}</dd>
                    </>
                ) : null}
                {!Array.isArray(log.meta)
                    ? Object.entries(log.meta).map(([k, v]) => (
                          <Fragment key={k}>
                              <dt>{k}</dt>
                              <dd className="font-mono break-all">
                                  {formatValue(v)}
                              </dd>
                          </Fragment>
                      ))
                    : null}
            </dl>
        </div>
    );
}
