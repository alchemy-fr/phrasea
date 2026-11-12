'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {PlusIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {OAuthClient} from '@/types/api';
import {createOAuthClient} from '@/lib/api/oauthClients';
import {isApiError} from '@/lib/api/http';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {FormRow, Input, Textarea} from '@alchemy/next-ui/ui/input';

const defaultClientId = 'databox-integration';

/**
 * A client ID made of the title of the integration (what Keycloak accepts:
 * letters, digits, ".", "_" and "-").
 */
export function clientIdOf(title: string | undefined): string {
    const slug = (title ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .replace(/[^a-z0-9._-]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^[-.]+|[-.]+$/g, '')
        .slice(0, 255);

    return slug || defaultClientId;
}

type Props = ModalProps<OAuthClient | undefined> & {
    /** Title of the integration the client is for */
    integrationTitle?: string;
};

type Errors = Partial<Record<'clientId' | 'name' | 'description', string>>;

/**
 * Creates the Keycloak client authorizing the users of an integration
 * (public, standard flow, allowed to redirect to every integration) without
 * leaving the integration form.
 */
export function CreateOAuthClientDialog({
    open,
    onOpenChange,
    resolve,
    integrationTitle,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [clientId, setClientId] = useState(() =>
        clientIdOf(integrationTitle)
    );
    const [name, setName] = useState(integrationTitle ?? '');
    const [description, setDescription] = useState('');
    const [errors, setErrors] = useState<Errors>({});

    const submit = async () => {
        let created: OAuthClient;
        try {
            created = await createOAuthClient({
                clientId: clientId.trim(),
                name: name.trim() || undefined,
                description: description.trim() || undefined,
            });
        } catch (e) {
            if (isApiError(e, 422)) {
                const next: Errors = {};
                for (const v of e.violations) {
                    const key = (
                        ['clientId', 'name', 'description'] as const
                    ).find(k => k === v.propertyPath);
                    if (key) {
                        next[key] = next[key]
                            ? `${next[key]}\n${v.message}`
                            : v.message;
                    }
                }
                if (Object.keys(next).length > 0) {
                    setErrors(next);

                    return false;
                }
            }
            throw e;
        }
        void queryClient.invalidateQueries({queryKey: ['oauth-clients']});
        toast.success(
            t('integration.config.oauth_client.created', 'OAuth client created')
        );
        resolve?.(created);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t(
                'integration.config.oauth_client.create_title',
                'New OAuth client'
            )}
            description={t(
                'integration.config.oauth_client.create_description',
                'A public Keycloak client with the standard flow, allowed to redirect to every integration.'
            )}
            submitLabel={t('integration.config.oauth_client.submit', 'Create')}
            submitIcon={<PlusIcon />}
            canSubmit={!!clientId.trim()}
            onSubmit={submit}
        >
            <FormRow
                label={t(
                    'integration.config.oauth_client.client_id',
                    'Client ID'
                )}
                htmlFor="create-oauth-client-id"
                error={errors.clientId}
            >
                <Input
                    id="create-oauth-client-id"
                    autoFocus
                    value={clientId}
                    onChange={e => {
                        setClientId(e.target.value);
                        setErrors(({clientId: _, ...rest}) => rest);
                    }}
                    aria-invalid={!!errors.clientId}
                    autoComplete="off"
                    className="font-mono"
                />
            </FormRow>
            <FormRow
                label={t('integration.config.oauth_client.name', 'Name')}
                htmlFor="create-oauth-client-name"
                error={errors.name}
            >
                <Input
                    id="create-oauth-client-name"
                    value={name}
                    onChange={e => setName(e.target.value)}
                    aria-invalid={!!errors.name}
                    autoComplete="off"
                />
            </FormRow>
            <FormRow
                label={t(
                    'integration.config.oauth_client.description',
                    'Description'
                )}
                htmlFor="create-oauth-client-description"
                error={errors.description}
            >
                <Textarea
                    id="create-oauth-client-description"
                    value={description}
                    onChange={e => setDescription(e.target.value)}
                    aria-invalid={!!errors.description}
                />
            </FormRow>
        </FormDialog>
    );
}
