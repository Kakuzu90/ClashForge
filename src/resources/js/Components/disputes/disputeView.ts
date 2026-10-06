import type { AlertKind } from '@/Components/ui/UiAlert.vue';
import type { PillTone } from '@/Components/ui/UiPill.vue';
import { formatDateTime } from '@/Composables/useDateTime';

type Dispute = App.Domain.PlayerAccounts.Data.PartyDisputeData;
type Outcome = App.Domain.PlayerAccounts.Enums.PartyDisputeOutcome;

export interface DisputeMessage {
    kind: AlertKind;
    title: string;
    body: string;
}

/** Where a closed dispute sends the party next: their account, or the token path of the attach flow. */
export type OutcomeLink = 'account' | 'attach' | null;

const STATUS_TONE: Record<App.Domain.PlayerAccounts.Enums.DisputeStatus, PillTone> = {
    open: 'info',
    awaiting_holder: 'info',
    awaiting_claimant: 'info',
    awaiting_admin: 'warning',
    resolved_transfer: 'success',
    auto_resolved: 'success',
    resolved_denied: 'neutral',
    withdrawn: 'neutral',
    resolved_suspended: 'danger',
};

export const statusTone = (dispute: Dispute): PillTone => STATUS_TONE[dispute.status] ?? 'neutral';

function by(dispute: Dispute): string {
    return dispute.deadline ? formatDateTime(dispute.deadline) : 'soon';
}

/**
 * What a running dispute waits for, told to the party viewing it (specs/13 §5). The other party is
 * never named (owner decision 2026-10-06, P2-16).
 */
function running(dispute: Dispute): DisputeMessage {
    const tag = dispute.tag;
    const holder = dispute.role === 'holder';

    if (dispute.waitingOn === 'admin') {
        return holder
            ? {
                  kind: 'info',
                  title: 'The admins are reviewing the claim',
                  body: `${tag} stays yours until they decide. A new in-game API token still ends the review at once.`,
              }
            : { kind: 'info', title: 'The admins are reviewing your claim', body: 'We let you know as soon as they decide.' };
    }

    if (dispute.waitingOn === dispute.role) {
        if (holder && dispute.status === 'open') {
            return {
                kind: 'warning',
                title: 'Your answer is needed',
                body: `Another player says ${tag} is theirs. Verify it with a new in-game API token to end this at once, or tell the admins why it is yours. Answer by ${by(dispute)}; after that the admins decide without you.`,
            };
        }

        return holder
            ? {
                  kind: 'warning',
                  title: 'The admins need more from you',
                  body: `Show why ${tag} is yours, or verify it with a new in-game API token. Answer by ${by(dispute)}.`,
              }
            : {
                  kind: 'warning',
                  title: 'The admins need more from you',
                  body: `Show why ${tag} is yours. Answer by ${by(dispute)}, or your claim is closed.`,
              };
    }

    return holder
        ? { kind: 'info', title: 'Waiting for the other player', body: `The admins asked the other player for more. ${tag} stays yours meanwhile.` }
        : {
              kind: 'info',
              title: 'Waiting for the holder',
              body:
                  dispute.status === 'open'
                      ? 'The holder has been told and has a few days to answer. The admins review your claim after that either way. If you can get into the game, verifying with a token is faster.'
                      : 'The admins asked the holder for more. We let you know once they decide.',
          };
}

const OUTCOME_BODY: Record<Outcome, (tag: string) => string> = {
    transferred_to_you: (tag) => `${tag} is now verified on your Clash Commons account.`,
    released_to_you: (tag) => `The holder gave ${tag} up, so it is now verified on your Clash Commons account.`,
    transferred_away: () => 'If the account is yours, verify it again with a new in-game API token.',
    released_by_you: (tag) => `${tag} is now verified on the other player's account.`,
    denied: (tag) => `${tag} stays with its holder. If it is yours, you can still verify it with a new in-game API token.`,
    denied_token: () => 'A token from the game ends a dispute. If the account is yours, secure it in game, then verify it with a new token.',
    kept: (tag) => `The admins reviewed the claim. ${tag} stays on your Clash Commons account.`,
    kept_token: (tag) => `Your token ended the dispute. ${tag} stays on your Clash Commons account.`,
    suspended: (tag) => `Nobody can verify ${tag} on Clash Commons for now.`,
    withdrawn_by_you: (tag) => `${tag} stays with its holder. You cannot dispute it again for a while.`,
    withdrawn: (tag) => `The claim to ${tag} was withdrawn. It stays on your Clash Commons account.`,
    withdrawn_inactive: () => 'The admins asked you for more and got no answer in time.',
    verified_by_token: (tag) => `${tag} was verified with an in-game API token, which ends a dispute.`,
};

const OUTCOME_KIND: Partial<Record<Outcome, AlertKind>> = {
    transferred_to_you: 'success',
    released_to_you: 'success',
    kept: 'success',
    kept_token: 'success',
    suspended: 'danger',
};

export function disputeMessage(dispute: Dispute): DisputeMessage {
    if (dispute.outcome === null) {
        return running(dispute);
    }

    return {
        kind: OUTCOME_KIND[dispute.outcome] ?? 'info',
        title: dispute.outcomeLabel ?? dispute.statusLabel,
        body: OUTCOME_BODY[dispute.outcome](dispute.tag),
    };
}

export function outcomeLink(dispute: Dispute): OutcomeLink {
    switch (dispute.outcome) {
        case 'transferred_to_you':
        case 'released_to_you':
        case 'kept':
        case 'kept_token':
            return dispute.accountUlid ? 'account' : null;
        case 'transferred_away':
        case 'denied':
        case 'denied_token':
            return 'attach';
        default:
            return null;
    }
}
