import {
    Layout,
    SecurityMethod,
    type PublicationConfig,
    type TermsConfig,
} from '@/types/api';

/**
 * The configuration as edited. `null` leaves a value unset: a publication
 * then inherits it from its profile. The security method of a publication
 * set to "public" on purpose is `''`, so that it overrides the profile.
 */
export type ConfigDraft = {
    enabled: boolean | null;
    publiclyListed: boolean | null;
    downloadEnabled: boolean | null;
    downloadViaEmail: boolean | null;
    includeDownloadTermsInArchive: boolean | null;
    beginsAt: string | null;
    expiresAt: string | null;
    layout: Layout | null;
    securityMethod: SecurityMethod | '' | null;
    /** A new password, empty to keep the current one */
    password: string;
    /** Whether a password is already set (it is never read back) */
    hasPassword: boolean;
    terms: TermsConfig | null;
    downloadTerms: TermsConfig | null;
};

export type InheritableField = keyof Omit<
    ConfigDraft,
    'password' | 'hasPassword'
>;

export const PUBLIC_METHOD = '';

function isSet(terms: TermsConfig | null | undefined): terms is TermsConfig {
    return (
        !!terms &&
        ((terms.enabled !== null && terms.enabled !== undefined) ||
            !!terms.text ||
            !!terms.url)
    );
}

export function toDraft(config: PublicationConfig | undefined): ConfigDraft {
    return {
        enabled: config?.enabled ?? null,
        publiclyListed: config?.publiclyListed ?? null,
        downloadEnabled: config?.downloadEnabled ?? null,
        downloadViaEmail: config?.downloadViaEmail ?? null,
        includeDownloadTermsInArchive:
            config?.includeDownloadTermsInArchive ?? null,
        beginsAt: config?.beginsAt ?? null,
        expiresAt: config?.expiresAt ?? null,
        layout: config?.layout ?? null,
        securityMethod:
            (config?.securityMethod as ConfigDraft['securityMethod']) ?? null,
        password: '',
        hasPassword: !!config?.hasPassword,
        terms: isSet(config?.terms)
            ? {
                  enabled: config!.terms!.enabled ?? false,
                  text: config!.terms!.text ?? null,
                  url: config!.terms!.url ?? null,
              }
            : null,
        downloadTerms: isSet(config?.downloadTerms)
            ? {
                  enabled: config!.downloadTerms!.enabled ?? false,
                  text: config!.downloadTerms!.text ?? null,
                  url: config!.downloadTerms!.url ?? null,
              }
            : null,
    };
}

const unsetTerms: TermsConfig = {enabled: null, text: null, url: null};

/** The configuration sent to the API */
export function fromDraft(draft: ConfigDraft): PublicationConfig {
    const method = draft.securityMethod;

    return {
        enabled: draft.enabled,
        publiclyListed: draft.publiclyListed,
        downloadEnabled: draft.downloadEnabled,
        downloadViaEmail: draft.downloadViaEmail,
        includeDownloadTermsInArchive: draft.includeDownloadTermsInArchive,
        beginsAt: draft.beginsAt,
        expiresAt: draft.expiresAt,
        layout: draft.layout,
        securityMethod: method as SecurityMethod | null,
        // Without a new password, the API keeps the current one
        securityOptions:
            method === SecurityMethod.Password && draft.password
                ? {password: draft.password}
                : [],
        terms: draft.terms
            ? {
                  enabled: !!draft.terms.enabled,
                  text: draft.terms.enabled ? draft.terms.text || null : null,
                  url: draft.terms.enabled ? draft.terms.url || null : null,
              }
            : unsetTerms,
        downloadTerms: draft.downloadTerms
            ? {
                  enabled: !!draft.downloadTerms.enabled,
                  text: draft.downloadTerms.enabled
                      ? draft.downloadTerms.text || null
                      : null,
                  url: draft.downloadTerms.enabled
                      ? draft.downloadTerms.url || null
                      : null,
              }
            : unsetTerms,
    };
}

/**
 * The value a field takes when it starts overriding the profile: the one of
 * the profile, else a default. A date cannot be overridden with "no date":
 * the API reads an empty date as unset, the profile's one applies.
 */
export function overrideValue<F extends InheritableField>(
    field: F,
    profile: ConfigDraft,
    now: Date = new Date()
): NonNullable<ConfigDraft[F]> {
    const inherited = profile[field];
    if (inherited !== null) {
        return inherited as NonNullable<ConfigDraft[F]>;
    }
    const day = 24 * 3600 * 1000;
    const defaults: {[K in InheritableField]: NonNullable<ConfigDraft[K]>} = {
        enabled: false,
        publiclyListed: false,
        downloadEnabled: false,
        downloadViaEmail: false,
        includeDownloadTermsInArchive: false,
        beginsAt: now.toISOString(),
        expiresAt: new Date(now.getTime() + 30 * day).toISOString(),
        layout: Layout.Grid,
        securityMethod: PUBLIC_METHOD,
        terms: {enabled: false, text: null, url: null},
        downloadTerms: {enabled: false, text: null, url: null},
    };

    return defaults[field] as NonNullable<ConfigDraft[F]>;
}

/** Problems preventing the configuration from being saved */
export function validateDraft(
    draft: ConfigDraft
): Partial<Record<keyof ConfigDraft, string>> {
    const errors: Partial<Record<keyof ConfigDraft, string>> = {};
    if (
        draft.securityMethod === SecurityMethod.Password &&
        !draft.password &&
        !draft.hasPassword
    ) {
        errors.password = 'required';
    }
    if (
        draft.beginsAt &&
        draft.expiresAt &&
        new Date(draft.beginsAt) >= new Date(draft.expiresAt)
    ) {
        errors.expiresAt = 'before_begin';
    }

    return errors;
}
