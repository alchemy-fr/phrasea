'use client';

import {useId, useState} from 'react';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {WandSparklesIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {FormRow} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {Checkbox, LabeledControl} from '@alchemy/next-ui/ui/controls';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@alchemy/next-ui/ui/dialog';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {getDataboxFields, getFormSchemas} from '@/lib/api/uploader';
import {
    fromLiForm,
    type ConversionResult,
} from '@/features/form-builder/convert/fromLiForm';
import type {FormDefinition} from '@/features/form-builder/types';
import type {LiFormSchema} from '@/features/liform/types';
import type {FormSchema} from '@/types/api';
import {useDataboxWorkspaces, useTargetWorkspace} from './databox';
import {formSchemasQueryKey} from './queryKeys';

const NO_WORKSPACE = '__none__';

/**
 * Turns the LiForm schema being edited into a form of the builder, opened
 * unsaved in the builder.
 */
export function ConvertLiFormDialog({
    open,
    onOpenChange,
    schemaId,
    targetId,
    locale,
    liForm,
    onConverted,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    schemaId?: string;
    targetId: string | undefined;
    locale: string | null;
    liForm: LiFormSchema;
    onConverted: (definition: FormDefinition) => void;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const workspaceSelectId = useId();
    const workspaces = useDataboxWorkspaces();
    const targetWorkspace = useTargetWorkspace(targetId);
    const [workspaceId, setWorkspaceId] = useState<string | null>();
    const chosenWorkspace =
        workspaceId === undefined
            ? (targetWorkspace.data ?? null)
            : workspaceId;
    const siblings = useQuery({
        queryKey: formSchemasQueryKey,
        queryFn: getFormSchemas,
        enabled: open && !!targetId,
        select: (list: FormSchema[]) =>
            list.filter(
                s =>
                    s.target.id === targetId &&
                    s.id !== schemaId &&
                    s.format !== 'builder'
            ),
    });
    const [translations, setTranslations] = useState<string[]>([]);
    const [result, setResult] = useState<ConversionResult>();
    const [error, setError] = useState<string>();
    const [converting, setConverting] = useState(false);

    const convert = async () => {
        setConverting(true);
        setError(undefined);
        try {
            const fields = chosenWorkspace
                ? await queryClient.fetchQuery({
                      queryKey: [
                          'databox',
                          'workspaces',
                          chosenWorkspace,
                          'fields',
                      ],
                      queryFn: () => getDataboxFields(chosenWorkspace),
                      staleTime: 60_000,
                  })
                : [];
            setResult(
                fromLiForm(
                    {locale, schema: liForm},
                    fields,
                    chosenWorkspace,
                    (siblings.data ?? [])
                        .filter(s => translations.includes(s.id))
                        .map(s => ({
                            locale: s.locale ?? null,
                            schema: s.data as LiFormSchema,
                        }))
                )
            );
        } catch (e) {
            setError(getErrorMessage(e));
        } finally {
            setConverting(false);
        }
    };

    const warningLabel = (code: string) =>
        ({
            restricted_entities: t(
                'builder.convert.warning.restricted_entities',
                'offered some values of the entity list: the field offers them all.'
            ),
            type_mismatch: t(
                'builder.convert.warning.type_mismatch',
                'does not fit the type of the Databox attribute: kept as a custom field.'
            ),
            unknown_attribute: t(
                'builder.convert.warning.unknown_attribute',
                'is no attribute of the workspace: kept as a custom field.'
            ),
            unsupported: t(
                'builder.convert.warning.unsupported',
                'has no equivalent in the builder (oneOf, nested object): left out.'
            ),
        })[code] ?? code;

    return (
        <Dialog
            open={open}
            onOpenChange={o => {
                onOpenChange(o);
                if (!o) {
                    setResult(undefined);
                }
            }}
        >
            <DialogContent size="lg" data-testid="convert-liform-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            'builder.convert.title',
                            'Convert to the form builder'
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'builder.convert.description',
                            'The fields keep their keys: the data sent to the target stays the same. Nothing is saved until you save the form.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody>
                    {!result ? (
                        <>
                            <FormRow
                                label={t(
                                    'builder.workspace',
                                    'Databox workspace'
                                )}
                                htmlFor={workspaceSelectId}
                                error={
                                    workspaces.error
                                        ? getErrorMessage(workspaces.error)
                                        : undefined
                                }
                                help={t(
                                    'builder.convert.workspace_help',
                                    'The keys matching an attribute of the workspace become Databox fields.'
                                )}
                            >
                                <SimpleSelect<string>
                                    id={workspaceSelectId}
                                    value={chosenWorkspace ?? NO_WORKSPACE}
                                    disabled={workspaces.isPending}
                                    onValueChange={v =>
                                        setWorkspaceId(
                                            v === NO_WORKSPACE ? null : v
                                        )
                                    }
                                    options={[
                                        {
                                            value: NO_WORKSPACE,
                                            label: t(
                                                'builder.no_workspace',
                                                'None'
                                            ),
                                        },
                                        ...(workspaces.data ?? []).map(w => ({
                                            value: w.id,
                                            label: w.name,
                                        })),
                                    ]}
                                />
                            </FormRow>
                            {siblings.data?.length ? (
                                <fieldset className="mb-4">
                                    <legend className="mb-1.5 text-sm font-medium">
                                        {t(
                                            'builder.convert.translations',
                                            'Take the translations of the schemas of this target in'
                                        )}
                                    </legend>
                                    <div className="flex flex-col gap-2">
                                        {siblings.data.map(s => (
                                            <LabeledControl
                                                key={s.id}
                                                label={
                                                    s.locale ||
                                                    t(
                                                        'admin.form_schemas.all_locales',
                                                        'All locales'
                                                    )
                                                }
                                            >
                                                <Checkbox
                                                    checked={translations.includes(
                                                        s.id
                                                    )}
                                                    onCheckedChange={checked =>
                                                        setTranslations(list =>
                                                            checked
                                                                ? [
                                                                      ...list,
                                                                      s.id,
                                                                  ]
                                                                : list.filter(
                                                                      id =>
                                                                          id !==
                                                                          s.id
                                                                  )
                                                        )
                                                    }
                                                />
                                            </LabeledControl>
                                        ))}
                                    </div>
                                </fieldset>
                            ) : null}
                            {error ? (
                                <Alert variant="destructive">{error}</Alert>
                            ) : null}
                        </>
                    ) : (
                        <div data-testid="convert-liform-report">
                            <p className="mb-3 text-sm">
                                {t(
                                    'builder.convert.done',
                                    '{{count}} fields converted.',
                                    {
                                        count: result.definition.fields.length,
                                    }
                                )}
                            </p>
                            {result.warnings.length ? (
                                <Alert variant="warning" className="mb-3">
                                    <ul className="list-disc pl-4 text-sm">
                                        {result.warnings.map(w => (
                                            <li key={`${w.key}-${w.code}`}>
                                                <code>{w.key}</code>{' '}
                                                {warningLabel(w.code)}
                                            </li>
                                        ))}
                                    </ul>
                                </Alert>
                            ) : null}
                            {siblings.data?.length ? (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'builder.convert.siblings',
                                        'The other schemas of this target are left as they are: users of their language still get them. Delete them once the converted form is saved.'
                                    )}
                                </p>
                            ) : null}
                        </div>
                    )}
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('builder.convert.cancel', 'Cancel')}
                    </Button>
                    {!result ? (
                        <Button
                            loading={converting}
                            data-testid="convert-liform-run"
                            onClick={() => void convert()}
                        >
                            <WandSparklesIcon />{' '}
                            {t('builder.convert.run', 'Convert')}
                        </Button>
                    ) : (
                        <Button
                            data-testid="convert-liform-open"
                            onClick={() => {
                                onConverted(result.definition);
                                onOpenChange(false);
                            }}
                        >
                            {t('builder.convert.open', 'Open in the builder')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
