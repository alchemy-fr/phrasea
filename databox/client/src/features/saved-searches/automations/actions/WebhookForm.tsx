'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {PlusIcon, Trash2Icon, WebhookIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {SimpleSelect} from '@/components/ui/select';
import {FieldError, FormRow, Input, Label} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import type {ActionDefinition, ActionFormProps} from './types';

type WebhookMethod = 'POST' | 'PUT';
type WebhookMode = 'per_batch' | 'summary';

type HeaderRow = {key: number; name: string; value: string};

let headerKey = 0;

function toRows(headers: unknown): HeaderRow[] {
    if (!headers || typeof headers !== 'object' || Array.isArray(headers)) {
        return [];
    }

    return Object.entries(headers as Record<string, unknown>).map(
        ([name, value]) => ({
            key: ++headerKey,
            name,
            value: String(value ?? ''),
        })
    );
}

/**
 * Headers with a name, as sent to the API (a map, the last row wins).
 */
export function toHeaders(rows: {name: string; value: string}[]) {
    const headers: Record<string, string> = {};
    for (const row of rows) {
        const name = row.name.trim();
        if (name) {
            headers[name] = row.value;
        }
    }

    return headers;
}

export function isHttpUrl(url: unknown): boolean {
    if (typeof url !== 'string') {
        return false;
    }
    try {
        const {protocol} = new URL(url.trim());

        return protocol === 'http:' || protocol === 'https:';
    } catch {
        return false;
    }
}

export function WebhookForm({value, onChange, errors}: ActionFormProps) {
    const {t} = useTranslation();
    const [rows, setRows] = useState<HeaderRow[]>(() => toRows(value.headers));

    const set = (patch: Record<string, unknown>) =>
        onChange({...value, ...patch});
    const updateRows = (next: HeaderRow[]) => {
        setRows(next);
        set({headers: toHeaders(next)});
    };

    return (
        <div>
            <FormRow
                label={t('automation.action.webhook.url', 'URL')}
                error={errors?.url}
                help={t(
                    'automation.action.webhook.url_help',
                    'Private and local addresses are not allowed.'
                )}
            >
                <Input
                    type="url"
                    value={value.url ?? ''}
                    placeholder="https://"
                    onChange={e => set({url: e.target.value})}
                />
            </FormRow>
            <FormRow
                label={t('automation.action.webhook.method', 'Method')}
                error={errors?.method}
            >
                <SimpleSelect<WebhookMethod>
                    value={value.method ?? 'POST'}
                    onValueChange={method => set({method})}
                    options={[
                        {value: 'POST', label: 'POST'},
                        {value: 'PUT', label: 'PUT'},
                    ]}
                />
            </FormRow>
            <FormRow
                label={t('automation.action.webhook.mode', 'Calls')}
                error={errors?.mode}
                help={
                    (value.mode ?? 'per_batch') === 'summary'
                        ? t(
                              'automation.action.webhook.mode_summary_help',
                              'One call at the end of the run, listing up to 10,000 processed assets (no call when no asset was processed). A failed call does not retry the assets.'
                          )
                        : t(
                              'automation.action.webhook.mode_per_batch_help',
                              'One call per batch of assets: when a call fails, the assets of the batch are retried at the next run.'
                          )
                }
            >
                <SimpleSelect<WebhookMode>
                    value={value.mode ?? 'per_batch'}
                    onValueChange={mode => set({mode})}
                    options={[
                        {
                            value: 'per_batch',
                            label: t(
                                'automation.action.webhook.mode_per_batch',
                                'One call per batch of assets'
                            ),
                        },
                        {
                            value: 'summary',
                            label: t(
                                'automation.action.webhook.mode_summary',
                                'One summary call at the end of the run'
                            ),
                        },
                    ]}
                />
            </FormRow>
            <FormRow
                label={t('automation.action.webhook.secret', 'Signing secret')}
                error={errors?.secret}
                help={t(
                    'automation.action.webhook.secret_help',
                    'When set, the body is signed with HMAC-SHA256 in the X-Phrasea-Signature header ("sha256=<hex>").'
                )}
            >
                <Input
                    type="password"
                    autoComplete="new-password"
                    value={value.secret ?? ''}
                    placeholder={
                        value.secretSet
                            ? t(
                                  'automation.action.webhook.secret_unchanged',
                                  'Unchanged'
                              )
                            : undefined
                    }
                    onChange={e => set({secret: e.target.value || null})}
                />
            </FormRow>
            <div className="mb-4 space-y-2">
                <Label>
                    {t('automation.action.webhook.headers', 'Headers')}
                </Label>
                {rows.map(row => (
                    <div key={row.key} className="flex items-center gap-2">
                        <Input
                            value={row.name}
                            placeholder={t(
                                'automation.action.webhook.header_name',
                                'Name'
                            )}
                            onChange={e =>
                                updateRows(
                                    rows.map(r =>
                                        r.key === row.key
                                            ? {...r, name: e.target.value}
                                            : r
                                    )
                                )
                            }
                        />
                        <Input
                            value={row.value}
                            placeholder={t(
                                'automation.action.webhook.header_value',
                                'Value'
                            )}
                            onChange={e =>
                                updateRows(
                                    rows.map(r =>
                                        r.key === row.key
                                            ? {...r, value: e.target.value}
                                            : r
                                    )
                                )
                            }
                        />
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={() =>
                                updateRows(rows.filter(r => r.key !== row.key))
                            }
                            title={t('common.remove', 'Remove')}
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                ))}
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        setRows([
                            ...rows,
                            {key: ++headerKey, name: '', value: ''},
                        ])
                    }
                >
                    <PlusIcon />
                    {t('automation.action.webhook.add_header', 'Add a header')}
                </Button>
                <FieldError>{errors?.headers}</FieldError>
            </div>
            <LabeledControl
                label={t(
                    'automation.action.webhook.include_attributes',
                    'Include the attributes of the assets'
                )}
            >
                <Switch
                    checked={!!value.includeAttributes}
                    onCheckedChange={includeAttributes =>
                        set({includeAttributes})
                    }
                />
            </LabeledControl>
        </div>
    );
}

export const webhookAction: ActionDefinition = {
    label: t => t('automation.action.webhook.label', 'Call a webhook'),
    icon: <WebhookIcon />,
    Form: WebhookForm,
    summary: (config, t) => {
        let host = '';
        try {
            host = new URL(config.url).host;
        } catch {
            // Incomplete URL
        }

        return host
            ? t('automation.action.webhook.summary', 'Call {{host}}', {host})
            : t('automation.action.webhook.label', 'Call a webhook');
    },
    defaultConfig: () => ({
        method: 'POST',
        mode: 'per_batch',
        headers: {},
        includeAttributes: false,
    }),
    isComplete: config => isHttpUrl(config.url),
};
