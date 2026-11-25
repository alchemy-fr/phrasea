---
title: 'Workspace terms'
---

# Workspace terms

A workspace can have **Terms & Conditions**: a text, translatable, or a PDF. Its users must
sign the current version before reading its content, and visitors of its public links accept
it before viewing the shared assets.

## Model

- `App\Entity\Core\TermsVersion`: one row per version of the terms of a workspace (`version`
  from 1), with the `text` and its translations, an optional PDF `file` and its `checksum`.
  When a PDF is set, it takes precedence over the text.
- `App\Entity\Core\TermsSignature`: who (`userId`) signed which version, and when. Signing a
  new version adds a row.

`App\Service\Workspace\TermsManager` manages them:

- the current terms are the latest version, unless it is empty (terms cleared);
- changing the text or its translations creates a new version (the PDF is kept); an empty
  text clears the terms;
- uploading a PDF creates a new version (the text is kept), unless it is identical
  (same SHA-256) to the current one; the upload must be a PDF;
- removing the PDF creates a new version without it;
- signing is idempotent.

`App\Service\Workspace\TermsPdfGenerator` renders text terms as a PDF on demand (Dompdf,
template `terms/terms_pdf.html.twig`, with the workspace name and logo, the version and the
date). PDF terms are served as they are.

## Where terms apply

- **Workspace content.** `WorkspaceVoter::READ` requires the signature of the current
  version (`READ_NO_TERMS` does not): an unsigned user gets a 403 on the assets, collections…
  of the workspace. Each new version must be signed again. Anonymous visitors of a public
  workspace are not asked to sign. The output field `termsUnsigned` tells the client that
  the user must sign; it is `false` for the workspace owner, whom the client does not
  prompt, while the voter has no owner exception.
- **Export.** The export dialog makes the user sign the terms of the workspaces of the
  exported assets first. With **"Attach the Terms & Conditions PDF to exports"**
  (`attachTermsToExports`), the PDF is added to the archive (see [export](./export.md)).
- **Shares.** The public page of a [share](./shares.md) shows the current terms in a
  blocking dialog. Visitors are anonymous: their acceptance is kept in the browser
  (`localStorage`, per share, with the accepted version), not signed.

## API

| Method and path                         | Security        | Effect |
|-----------------------------------------|-----------------|--------|
| `GET /workspaces/{id}`, `GET /workspaces-by-slug/{slug}` | `READ_NO_TERMS` | `terms` (`text` in the user locale, `rawText`, `translations`, `version`, `signed`, `attachToExports`, `pdfUrl`) and `termsUnsigned`. |
| `POST /workspaces/{id}/terms/sign`      | `READ_NO_TERMS` | Sign the current version; 400 without terms. Logged as "Terms & Conditions signed" in the [audit log](./audit-log.md). |
| `PATCH /workspaces/{id}`                | `EDIT`          | `terms` (empty clears them), `termsTranslations` (by locale), `attachTermsToExports`. |
| `POST /workspaces/{id}/terms`           | `EDIT`          | Upload the PDF (multipart, `application/pdf`, 20 MB at most). |
| `DELETE /workspaces/{id}/terms`         | `EDIT`          | Remove the PDF. |
| `GET /workspaces/{id}/terms.pdf`        | `READ_NO_TERMS` | The current terms as a PDF, inline, `terms-{slug}-v{version}.pdf`; 404 without terms. |

## Client

- **Signature.** On arrival, `features/workspaces/terms/WorkspaceTermsGate.tsx` shows, one
  workspace at a time, "You must accept the Terms & Conditions (version …) to access this
  workspace.", with "I have read and accept the Terms & Conditions", "Accept" and "Not now".
  PDF terms show "View Terms & Conditions (PDF)".
- **Management.** Workspace management → **Edit** tab, section "Terms & Conditions": "Text"
  (with its translations), "PDF" ("Upload a PDF", "Remove the PDF") and "Attach the Terms &
  Conditions PDF to exports". Changing the text or uploading a PDF asks the users to sign
  again.

## Administration and templates

The EasyAdmin pages **Terms Versions** (read-only) and **Terms Signatures** (deletable) list
them. Workspace templates carry the current text of the terms, not the PDF
(`App\Service\Workspace\Template\Section\TermsSection`).
