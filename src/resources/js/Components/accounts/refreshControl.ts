/**
 * The manual refresh control on the account page (FR-COC-9, P2-20). The server decides whether the
 * owner may refresh (`canRefresh`) and how long the cooldown has left; this only picks what the
 * button and its hint show.
 */
export type RefreshState = 'idle' | 'refreshing' | 'cooling' | 'unavailable';

export function refreshState({ processing, apiDown, waitSeconds }: { processing: boolean; apiDown: boolean; waitSeconds: number }): RefreshState {
    if (processing) {
        return 'refreshing';
    }
    if (apiDown) {
        return 'unavailable';
    }

    return waitSeconds > 0 ? 'cooling' : 'idle';
}

export function refreshHint(state: RefreshState, waitSeconds: number): string | null {
    if (state === 'unavailable') {
        return 'Refresh is paused while the game API is unavailable.';
    }
    if (state !== 'cooling') {
        return null;
    }

    const minutes = Math.max(1, Math.ceil(waitSeconds / 60));

    return `You can refresh again in ${minutes === 1 ? '1 minute' : `${minutes} minutes`}.`;
}
