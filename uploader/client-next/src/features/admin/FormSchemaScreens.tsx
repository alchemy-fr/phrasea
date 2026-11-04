'use client';

import Link from 'next/link';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {PencilIcon, PlusIcon, Trash2Icon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Alert, EmptyState} from '@/components/ui/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {useConfirm} from '@/components/ui/confirm';
import {Page} from '@/components/layout/AppShell';
import {
    deleteFormSchema,
    getFormSchema,
    getFormSchemas,
} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {getErrorMessage} from '@/lib/utils/errors';
import {FormSchemaEditor, formSchemasQueryKey} from './FormSchemaEditor';
import {RequirePermission} from './RequirePermission';

export function FormSchemaListScreen() {
    return (
        <RequirePermission permission="form_schema">
            <FormSchemaList />
        </RequirePermission>
    );
}

function FormSchemaList() {
    const {t} = useTranslation();
    const confirm = useConfirm();
    const queryClient = useQueryClient();
    const {data, error, isPending} = useQuery({
        queryKey: formSchemasQueryKey,
        queryFn: getFormSchemas,
    });

    return (
        <Page
            title={t('admin.form_schemas.title', 'Form Schemas')}
            actions={
                <Button asChild>
                    <Link
                        href={routes.formSchemaCreate()}
                        data-testid="form-schema-create"
                    >
                        <PlusIcon />{' '}
                        {t(
                            'admin.form_schemas.create',
                            'Create New Form Schema'
                        )}
                    </Link>
                </Button>
            }
        >
            {isPending ? (
                <FullPageLoader />
            ) : error ? (
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            ) : data.length === 0 ? (
                <EmptyState
                    title={t('admin.form_schemas.empty', 'No form schema yet.')}
                />
            ) : (
                <ul
                    data-testid="form-schema-list"
                    className="divide-y rounded-lg border"
                >
                    {data.map(schema => (
                        <li
                            key={schema.id}
                            data-testid="form-schema-row"
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="truncate font-medium">
                                    {schema.target.name}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {schema.locale ||
                                        t(
                                            'admin.form_schemas.all_locales',
                                            'All locales'
                                        )}
                                </div>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={routes.formSchemaEdit(schema.id)}>
                                    <PencilIcon /> {t('admin.edit', 'Edit')}
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                data-testid="form-schema-delete"
                                onClick={() =>
                                    void confirm({
                                        title: t(
                                            'admin.form_schemas.delete_title',
                                            'Delete Form Schema'
                                        ),
                                        description: t(
                                            'admin.form_schemas.delete_confirm',
                                            'Delete the form schema of {{target}} ({{locale}})?',
                                            {
                                                target: schema.target.name,
                                                locale:
                                                    schema.locale ||
                                                    t(
                                                        'admin.form_schemas.all_locales',
                                                        'All locales'
                                                    ),
                                            }
                                        ),
                                        confirmLabel: t(
                                            'admin.delete',
                                            'Delete'
                                        ),
                                        destructive: true,
                                        onConfirm: async () => {
                                            await deleteFormSchema(schema.id);
                                            await queryClient.invalidateQueries(
                                                {
                                                    queryKey:
                                                        formSchemasQueryKey,
                                                }
                                            );
                                        },
                                    })
                                }
                            >
                                <Trash2Icon /> {t('admin.delete', 'Delete')}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </Page>
    );
}

export function FormSchemaCreateScreen() {
    const {t} = useTranslation();

    return (
        <RequirePermission permission="form_schema">
            <Page title={t('admin.form_schemas.new', 'New Form Schema')} wide>
                <FormSchemaEditor />
            </Page>
        </RequirePermission>
    );
}

export function FormSchemaEditScreen({id}: {id: string}) {
    return (
        <RequirePermission permission="form_schema">
            <FormSchemaEdit id={id} />
        </RequirePermission>
    );
}

function FormSchemaEdit({id}: {id: string}) {
    const {t} = useTranslation();
    const {data, error, isPending} = useQuery({
        queryKey: [...formSchemasQueryKey, id],
        queryFn: () => getFormSchema(id),
        // The editor starts from what is saved, never from a stale copy
        staleTime: 0,
    });

    if (isPending) {
        return <FullPageLoader />;
    }
    if (error) {
        return (
            <Page>
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            </Page>
        );
    }

    return (
        <Page
            title={t('admin.form_schemas.edit', 'Form Schema of {{target}}', {
                target: data.target.name,
            })}
            wide
        >
            <FormSchemaEditor key={data.id} schema={data} />
        </Page>
    );
}
