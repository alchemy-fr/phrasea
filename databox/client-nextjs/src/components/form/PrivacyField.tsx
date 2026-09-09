'use client';

import {useTranslation} from 'react-i18next';
import {InfoIcon} from 'lucide-react';
import {Privacy} from '@/types/api';
import {SimpleSelect} from '@/components/ui/select';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {Label} from '@/components/ui/input';
import {Alert} from '@/components/ui/misc';
import {privacyLabels} from '@/features/attributes/types/registry';

type Level = 'secret' | 'private' | 'public';

function decompose(value: Privacy): {
    level: Level;
    workspaceOnly: boolean;
    authRequired: boolean;
} {
    switch (value) {
        case Privacy.PrivateInWorkspace:
            return {level: 'private', workspaceOnly: true, authRequired: true};
        case Privacy.PublicInWorkspace:
            return {level: 'public', workspaceOnly: true, authRequired: true};
        case Privacy.Private:
            return {level: 'private', workspaceOnly: false, authRequired: true};
        case Privacy.PublicForUsers:
            return {level: 'public', workspaceOnly: false, authRequired: true};
        case Privacy.Public:
            return {level: 'public', workspaceOnly: false, authRequired: false};
        case Privacy.Secret:
        default:
            return {level: 'secret', workspaceOnly: false, authRequired: true};
    }
}

function compose(
    level: Level,
    workspaceOnly: boolean,
    authRequired: boolean
): Privacy {
    if (level === 'secret') {
        return Privacy.Secret;
    }
    if (level === 'private') {
        return workspaceOnly ? Privacy.PrivateInWorkspace : Privacy.Private;
    }

    return workspaceOnly
        ? Privacy.PublicInWorkspace
        : authRequired
          ? Privacy.PublicForUsers
          : Privacy.Public;
}

/**
 * Privacy picker: level (secret / private / public) + scope flags. Options more
 * permissive than the inherited value are never below it.
 */
export function PrivacyField({
    value,
    onChange,
    inheritedPrivacy,
    disabled,
    allowUnset,
    label,
}: {
    value: Privacy | undefined;
    onChange: (value: Privacy | undefined) => void;
    inheritedPrivacy?: Privacy;
    disabled?: boolean;
    allowUnset?: boolean;
    label?: string;
}) {
    const {t} = useTranslation();
    const labels = privacyLabels(t);
    const effective = value ?? inheritedPrivacy ?? Privacy.Secret;
    const {level, workspaceOnly, authRequired} = decompose(effective);
    const inherited = inheritedPrivacy ?? Privacy.Secret;

    const update = (l: Level, w: boolean, a: boolean) =>
        onChange(Math.max(inherited, compose(l, w, a)) as Privacy);

    return (
        <div className="mb-4 space-y-2">
            <Label>{label ?? t('common.privacy', 'Privacy')}</Label>
            <SimpleSelect
                value={value === undefined && allowUnset ? 'unset' : level}
                disabled={disabled}
                onValueChange={v => {
                    if (v === 'unset') {
                        onChange(undefined);
                    } else {
                        update(v as Level, workspaceOnly, authRequired);
                    }
                }}
                options={[
                    ...(allowUnset
                        ? [
                              {
                                  value: 'unset',
                                  label: t(
                                      'form.privacy.not_set',
                                      'Not set (inherit)'
                                  ),
                              },
                          ]
                        : []),
                    {
                        value: 'secret',
                        label: t('form.privacy.secret', 'Secret'),
                        disabled: inherited > Privacy.Secret,
                    },
                    {
                        value: 'private',
                        label: t(
                            'form.privacy.private',
                            'Private (users can request access)'
                        ),
                        disabled:
                            inherited > Privacy.PrivateInWorkspace &&
                            inherited !== Privacy.Private,
                    },
                    {
                        value: 'public',
                        label: t('form.privacy.public', 'Public'),
                    },
                ]}
            />
            {level !== 'secret' && !(value === undefined && allowUnset) ? (
                <div className="flex flex-col gap-1.5 pl-1">
                    <LabeledControl
                        label={t(
                            'form.privacy.workspace_only',
                            'Only visible to workspace users'
                        )}
                    >
                        <Checkbox
                            checked={workspaceOnly}
                            disabled={
                                disabled ||
                                (inherited >= Privacy.Private &&
                                    inherited !== Privacy.PrivateInWorkspace &&
                                    inherited !== Privacy.PublicInWorkspace)
                            }
                            onCheckedChange={v =>
                                update(
                                    level,
                                    v === true,
                                    v === true ? true : authRequired
                                )
                            }
                        />
                    </LabeledControl>
                    {level === 'public' ? (
                        <LabeledControl
                            label={t(
                                'form.privacy.auth_required',
                                'User must be authenticated'
                            )}
                        >
                            <Checkbox
                                checked={authRequired}
                                disabled={
                                    disabled ||
                                    workspaceOnly ||
                                    inherited === Privacy.Public
                                }
                                onCheckedChange={v =>
                                    update(level, workspaceOnly, v === true)
                                }
                            />
                        </LabeledControl>
                    ) : null}
                </div>
            ) : null}
            <p className="text-xs text-muted-foreground">
                {t('form.privacy.effective', 'Effective privacy: {{label}}', {
                    label: labels[effective],
                })}
            </p>
            {inheritedPrivacy !== undefined &&
            inheritedPrivacy > Privacy.Secret ? (
                <Alert variant="info" icon={<InfoIcon />}>
                    {t(
                        'form.privacy.inherited',
                        'Privacy is inherited from the parent ({{label}}) and cannot be more restrictive.',
                        {
                            label: labels[inheritedPrivacy],
                        }
                    )}
                </Alert>
            ) : null}
        </div>
    );
}
