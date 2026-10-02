type VerifyResult = App.Domain.PlayerAccounts.Data.VerifyResultData;

/**
 * "in about 4 minutes" for a server wait in seconds; a minute at least, since the limits are hourly.
 */
export function waitText(seconds: number | null | undefined): string {
    if (!seconds || seconds <= 60) return 'in a minute';
    const minutes = Math.ceil(seconds / 60);
    return minutes >= 60 ? 'in about an hour' : `in about ${minutes} minutes`;
}

/**
 * What a refused token means and what to do next (specs/13 §3 step 8, specs/18 §6). Null on success.
 */
export function verifyMessage(result: VerifyResult | null): { kind: 'warning' | 'danger'; title: string; body: string } | null {
    switch (result?.outcome) {
        case 'invalid_token':
            return {
                kind: 'warning',
                title: 'That token did not work',
                body: 'Tokens expire in a few minutes. Copy a fresh one from the game and paste it again.',
            };
        case 'not_found':
            return { kind: 'warning', title: 'The game has no player with this tag', body: 'Check the tag in game and start again.' };
        case 'unavailable':
            return {
                kind: 'danger',
                title: 'Clash of Clans cannot be reached right now',
                body: `Nothing was saved. Try again ${waitText(result.retryAfter)}.`,
            };
        case 'rate_limited':
            return {
                kind: 'warning',
                title: 'Too many token attempts this hour',
                body: `Try again ${waitText(result.retryAfter)}.`,
            };
        default:
            return null;
    }
}
