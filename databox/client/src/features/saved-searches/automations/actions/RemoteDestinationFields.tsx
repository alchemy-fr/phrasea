'use client';

import {useTranslation} from 'react-i18next';
import type {AutomationActionConfig} from '@/types/api';
import {FormRow, Input, Textarea} from '@alchemy/next-ui/ui/input';
import {LabeledControl, Switch} from '@alchemy/next-ui/ui/controls';
import {Tabs, TabsList, TabsTrigger} from '@alchemy/next-ui/ui/misc';
import type {ActionFormProps} from './types';

export type RemoteExportProtocol = 's3' | 'sftp' | 'ftp';

export const remoteExportProtocols: RemoteExportProtocol[] = [
    's3',
    'sftp',
    'ftp',
];

export const protocolLabels: Record<RemoteExportProtocol, string> = {
    s3: 'S3',
    sftp: 'SFTP',
    ftp: 'FTP',
};

/**
 * A secret is never sent back by the API: "<field>Set" tells whether one is stored,
 * and an empty field keeps it.
 */
function SecretField({
    label,
    value,
    isSet,
    error,
    multiline,
    onChange,
}: {
    label: string;
    value: string | undefined | null;
    isSet: boolean;
    error?: string;
    multiline?: boolean;
    onChange: (value: string) => void;
}) {
    const {t} = useTranslation();
    const placeholder = isSet
        ? t('automation.action.remote_export.secret_unchanged', 'Unchanged')
        : undefined;

    return (
        <FormRow label={label} error={error}>
            {multiline ? (
                <Textarea
                    value={value ?? ''}
                    placeholder={placeholder}
                    autoComplete="off"
                    spellCheck={false}
                    className="font-mono text-xs"
                    onChange={e => onChange(e.target.value)}
                />
            ) : (
                <Input
                    type="password"
                    value={value ?? ''}
                    placeholder={placeholder}
                    autoComplete="new-password"
                    onChange={e => onChange(e.target.value)}
                />
            )}
        </FormRow>
    );
}

/**
 * The destination of an export action (see RemoteDestinationConfig in the API):
 * a "protocol" and its options under its key.
 */
export function RemoteDestinationFields({
    value,
    onChange,
    errors,
}: ActionFormProps) {
    const {t} = useTranslation();
    const protocol: RemoteExportProtocol = value.protocol ?? 's3';
    const options: AutomationActionConfig = value[protocol] ?? {};

    const setOption = (patch: AutomationActionConfig) =>
        onChange({...value, [protocol]: {...options, ...patch}});
    const error = (key: string) => errors?.[`${protocol}.${key}`];

    const text = (key: string, label: string, placeholder?: string) => (
        <FormRow label={label} error={error(key)}>
            <Input
                value={options[key] ?? ''}
                placeholder={placeholder}
                onChange={e => setOption({[key]: e.target.value})}
            />
        </FormRow>
    );
    const port = (defaultPort: number) => (
        <FormRow
            label={t('automation.action.remote_export.port', 'Port')}
            error={error('port')}
        >
            <Input
                type="number"
                min={1}
                max={65535}
                value={options.port ?? ''}
                placeholder={String(defaultPort)}
                onChange={e =>
                    setOption({
                        port: e.target.value
                            ? parseInt(e.target.value, 10)
                            : null,
                    })
                }
            />
        </FormRow>
    );
    const secret = (key: string, label: string, multiline?: boolean) => (
        <SecretField
            label={label}
            value={options[key]}
            isSet={!!options[`${key}Set`]}
            error={error(key)}
            multiline={multiline}
            onChange={v => setOption({[key]: v})}
        />
    );
    const toggle = (key: string, label: string, defaultValue: boolean) => (
        <LabeledControl label={label} className="mb-4">
            <Switch
                checked={options[key] ?? defaultValue}
                onCheckedChange={v => setOption({[key]: v})}
            />
        </LabeledControl>
    );

    return (
        <>
            <FormRow
                label={t(
                    'automation.action.remote_export.protocol',
                    'Destination'
                )}
                error={errors?.protocol}
            >
                <Tabs
                    value={protocol}
                    onValueChange={p =>
                        onChange({
                            ...value,
                            protocol: p as RemoteExportProtocol,
                        })
                    }
                >
                    <TabsList>
                        {remoteExportProtocols.map(p => (
                            <TabsTrigger key={p} value={p}>
                                {protocolLabels[p]}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            </FormRow>

            {protocol === 's3' ? (
                <>
                    {text(
                        'bucket',
                        t('automation.action.remote_export.bucket', 'Bucket')
                    )}
                    {text(
                        'region',
                        t('automation.action.remote_export.region', 'Region'),
                        'us-east-1'
                    )}
                    {text(
                        'endpoint',
                        t(
                            'automation.action.remote_export.endpoint',
                            'Endpoint (S3 compatible storage)'
                        ),
                        'https://'
                    )}
                    {toggle(
                        'pathStyle',
                        t(
                            'automation.action.remote_export.path_style',
                            'Path-style URLs'
                        ),
                        false
                    )}
                    {text(
                        'accessKeyId',
                        t(
                            'automation.action.remote_export.access_key_id',
                            'Access key ID'
                        )
                    )}
                    {secret(
                        'secretAccessKey',
                        t(
                            'automation.action.remote_export.secret_access_key',
                            'Secret access key'
                        )
                    )}
                </>
            ) : null}

            {protocol === 'sftp' || protocol === 'ftp' ? (
                <>
                    {text(
                        'host',
                        t('automation.action.remote_export.host', 'Host'),
                        'files.example.com'
                    )}
                    {port(protocol === 'sftp' ? 22 : 21)}
                    {text(
                        'username',
                        t(
                            'automation.action.remote_export.username',
                            'Username'
                        )
                    )}
                    {secret(
                        'password',
                        t(
                            'automation.action.remote_export.password',
                            'Password'
                        )
                    )}
                </>
            ) : null}

            {protocol === 'sftp' ? (
                <>
                    {secret(
                        'privateKey',
                        t(
                            'automation.action.remote_export.private_key',
                            'Private key'
                        ),
                        true
                    )}
                    {secret(
                        'passphrase',
                        t(
                            'automation.action.remote_export.passphrase',
                            'Private key passphrase'
                        )
                    )}
                    {text(
                        'hostFingerprint',
                        t(
                            'automation.action.remote_export.host_fingerprint',
                            'Host key fingerprint (recommended)'
                        ),
                        'SHA256:…'
                    )}
                </>
            ) : null}

            {protocol === 'ftp' ? (
                <>
                    {toggle(
                        'ssl',
                        t(
                            'automation.action.remote_export.ssl',
                            'Secure connection (FTPS)'
                        ),
                        false
                    )}
                    {toggle(
                        'passive',
                        t(
                            'automation.action.remote_export.passive',
                            'Passive mode'
                        ),
                        true
                    )}
                </>
            ) : null}

            {protocol === 'sftp' || protocol === 'ftp'
                ? text(
                      'root',
                      t(
                          'automation.action.remote_export.root',
                          'Root directory'
                      ),
                      '/'
                  )
                : null}
        </>
    );
}

function isSecretSet(options: AutomationActionConfig, key: string): boolean {
    return !!options[key] || !!options[`${key}Set`];
}

/**
 * Whether the destination can be submitted (the API validates it anyway).
 */
export function isRemoteDestinationComplete(
    config: AutomationActionConfig
): boolean {
    const protocol = config.protocol as RemoteExportProtocol | undefined;
    const options: AutomationActionConfig =
        (protocol && config[protocol]) ?? {};
    switch (protocol) {
        case 's3':
            return (
                !!options.bucket?.trim() &&
                !!options.accessKeyId?.trim() &&
                isSecretSet(options, 'secretAccessKey')
            );
        case 'sftp':
            return (
                !!options.host?.trim() &&
                !!options.username?.trim() &&
                (isSecretSet(options, 'password') ||
                    isSecretSet(options, 'privateKey'))
            );
        case 'ftp':
            return !!options.host?.trim() && !!options.username?.trim();
        default:
            return false;
    }
}

/**
 * The protocol label and the bucket or host of the destination, if set.
 */
export function describeRemoteDestination(
    config: AutomationActionConfig
): {protocol: string; destination: string} | undefined {
    const protocol = config.protocol as RemoteExportProtocol | undefined;
    const options = (protocol && config[protocol]) ?? {};
    const destination =
        protocol === 's3' ? options.bucket : (options.host ?? undefined);

    return protocol && destination
        ? {protocol: protocolLabels[protocol] ?? protocol, destination}
        : undefined;
}
