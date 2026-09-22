/**
 * The application chrome that never goes away: the top bar (logo, where the
 * user is, the way back, notifications, settings and the user menu).
 *
 * Screens that take over the whole window — the asset viewer, the basket
 * view, the batch attribute editor — are laid out *below* it with
 * `belowTopBar` instead of `inset-0`, so that row stays visible and usable.
 * Both constants describe the same height and must stay in sync.
 */
export const topBarHeight = 'h-12';

export const belowTopBar = 'fixed inset-x-0 top-12 bottom-0';
