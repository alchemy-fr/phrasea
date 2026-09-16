/**
 * On a soft navigation, a slot that matches nothing keeps rendering its
 * previous state (`default.tsx` only applies to full page loads). Without this
 * catch-all, leaving a dialog's route — closing it pushes the URL it was
 * opened from — would leave the dialog mounted: merely hidden, recorded as
 * such in the new history entry, and not reopened by the back button since
 * React would reuse that same closed instance.
 *
 * Intercepting routes take precedence over it, so dialogs still open.
 */
export default function ModalSlotCatchAll() {
    return null;
}
