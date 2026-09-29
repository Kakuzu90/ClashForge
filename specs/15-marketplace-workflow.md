# 15 — Marketplace Workflow & Risk Analysis

Phase 6. This document leads with the risk analysis because the risks determine the design.

> **Note:** this is engineering analysis, not legal advice. Before launching anything involving
> payments, get a qualified lawyer in the operating jurisdiction to review the model, the terms and
> the escrow question specifically.

---

## 1. Hard boundary: what may never be sold

Rejected at listing validation, screened by the prohibited-term lexicon, and a Critical report
reason ([12](12-moderation-system.md)):

| Prohibited | Why |
|---|---|
| Clash of Clans accounts — sale, trade, rent, gift, "adoption" | Violates Supercell's ToS; the single biggest existential risk to the platform |
| Account credentials or Supercell ID transfer | Same |
| Gems, resources, event passes for real money | RMT; violates ToS |
| Boosting/pushing that requires logging into the buyer's account | Requires credential sharing; violates ToS |
| Clan slot sales in "paid CWL" clans where money buys in-game reward eligibility | RMT-adjacent, and the primary scam vector in this community |
| Anything involving gambling, loot-box resale or wagering | Regulatory exposure in most jurisdictions |
| Cheats, bots, modded clients, third-party automation | ToS violation and malware vector |

## 2. Permitted services

Creative and advisory work where the deliverable is a file or a conversation, and no game account
changes hands:

custom base layout design · base review and feedback · attack strategy coaching (spectating and
advising, never playing) · clan graphics and badges · banners and overlays · video editing ·
tournament graphics and bracket design · clan Discord setup and bot configuration · thumbnail design.

Each maps to a `category` value. New categories require admin approval, never a free-text field.

---

## 3. Payment and escrow: the analysis

### The middleman/escrow model as described

"Platform holds the buyer's money until the seller delivers, then releases it." This is the model
that makes users feel safe. It is also the one that carries the most risk.

### Risk 1 — Money transmission / e-money regulation (**severity: critical**)

Holding funds belonging to two other parties and releasing them on a condition is, in many
jurisdictions, regulated activity: money transmission (US, state-by-state licensing), payment
institution or e-money institution authorisation (EU/UK), or the local equivalent (in the
Philippines, BSP registration as an operator of a payment system / money service business).

Consequences of getting it wrong range from account freezes to criminal liability. The usual
mitigations:
- **Do not touch the funds.** Use a payment provider's marketplace product where the provider is
  the regulated entity (Stripe Connect with separate charges & transfers, PayPal Commerce Platform,
  Adyen for Platforms, or a local equivalent). Money flows provider → seller; the platform only
  instructs.
- **Even then**, delayed payouts and dispute arbitration can still look like escrow. Stripe Connect
  explicitly supports holding funds for a delayed transfer, which shifts the regulatory burden to
  Stripe — this is the supported path, but it requires the platform to be onboarded as a Connect
  platform and to satisfy their underwriting.

### Risk 2 — Payment provider acceptance (**severity: high**)

Gaming-adjacent marketplaces with user-generated listings are a **high-risk merchant category**.
Realistic outcomes:
- Stripe/PayPal accounts frozen or terminated when account-trading listings are found, even if the
  platform prohibits them. Enforcement is automated and unforgiving.
- Rolling reserves (5–10% held for 90–180 days) applied to high-risk marketplaces.
- Seller onboarding requires KYC (identity documents, tax details). A large share of this
  audience is under 18 and cannot complete KYC at all.

**The under-18 problem is decisive.** A meaningful fraction of the CoC community are minors. Minors
cannot enter binding contracts in most jurisdictions, cannot pass KYC, and cannot be paid out by
Stripe Connect. A marketplace that pays sellers effectively requires age verification on the seller
side, which is a whole compliance project of its own.

### Risk 3 — Chargebacks and fraud (**severity: high**)

Digital goods have essentially no chargeback defence: there is no shipment tracking, no signature.
A buyer can receive a base layout and file "item not received". Card networks side with the buyer by
default. With escrow, the platform eats the loss and the fee.

Fraud patterns to expect: stolen-card purchases to launder value into a seller payout; collusive
buyer/seller pairs cashing out stolen cards; "friendly fraud" after delivery.

### Risk 4 — Tax and reporting (**severity: medium**)

Facilitating payments creates reporting obligations (1099-K in the US above thresholds, DAC7 in the
EU for platform sellers, VAT/GST on the platform's own commission, and possibly on the service
itself depending on the jurisdiction pair). This needs a real accountant, not a best guess.

### Risk 5 — Dispute arbitration burden (**severity: medium, but it is the one that actually breaks you**)

"The base isn't good enough" is not objectively adjudicable. Every escrow dispute becomes a
judgement call made by a volunteer moderator, with real money attached and an angry party on at
least one side. At a few hundred orders a month this consumes more staff time than all other
moderation combined.

### Risk 6 — Supercell relationship (**severity: high**)

Commercialising a community around someone else's IP invites scrutiny. Supercell's Fan Content
Policy permits non-commercial fan content and tolerates a lot, but a marketplace that takes a
commission on CoC-related services is closer to the line. Running services without taking a
commission is meaningfully safer than monetising the transaction.

---

## 4. Recommendation

### Stage 1 (Phase 6) — **Discovery marketplace, no payments.** Ship this.

The platform hosts listings, seller profiles, order workflow, order-scoped messaging, delivery of
files and reviews. **Money is arranged directly between the parties, off-platform.** The UI says so
explicitly, repeatedly, and without weasel words:

> Clash Commons does not process payments and does not hold funds. Any payment you arrange is
> between you and the other person, at your own risk. We cannot recover your money.

What this buys: all of the product value (discovery, reputation, workflow, proof of delivery) with
**none** of the money-transmission, KYC, chargeback or tax exposure. It is also the only version
that works for the under-18 majority of this audience.

What it costs: scams still happen off-platform, and the platform's reputation absorbs some of that.
Mitigated by reputation being the product — a seller's review history, completed-order count and
verified account age are exactly the signals that make off-platform payment less risky — plus
prominent scam guidance, a `scam` report reason at High priority, and permanent bans for confirmed
scammers.

### Stage 2 (only if Stage 1 shows real volume) — **Provider-managed payments.**

Preconditions, all of them, before a line of payment code is written:
1. ≥300 completed orders/month sustained for 3 months — proof the demand is real.
2. Legal review completed in the operating jurisdiction.
3. A payment provider (Stripe Connect or equivalent) onboarded **with full disclosure** of the
   vertical, and their written acceptance.
4. Seller KYC and an 18+ requirement for paid sellers, enforced at onboarding.
5. A funded chargeback reserve and an accepted loss budget.
6. A written dispute policy with objective criteria, and staff time budgeted for it.

Implementation shape if it proceeds: **separate charges and transfers** via Stripe Connect. The
provider holds the funds; the platform instructs a transfer on order completion. The platform never
has a balance owed to users in its own bank account. Commission is taken as an application fee.

### Stage 3 — Escrow-like holds

Only inside the provider's supported delayed-transfer mechanism, never as platform-held funds, and
only with legal sign-off on the specific arrangement.

### What we will not do, at any stage
Hold user funds in the platform's own accounts · process card payments directly · support crypto
payments · offer buyer protection or refund guarantees we cannot fund · take a commission before
the legal review is done.

---

## 5. Workflow (Stage 1 design)

### Seller onboarding
```
1. Apply: verified email, active status, ≥1 verified CoC account ≥30 days old,
          no sanctions in the last 90 days
2. Application: headline, bio, categories, portfolio samples (≤5 images), links to prior work
3. Manual admin review — approve / reject with reason / request more info
4. Approved ──▶ seller_profiles.status = 'approved'; may publish listings
```
Manual review is deliberate: the seller gate is the platform's main defence against the prohibited
categories, and automating it early would be a false economy.

### Listing lifecycle
```
draft ──▶ pending_review ──▶ active ⇄ paused
                   └──▶ rejected (reason)          active ──▶ removed (moderation)
```
- Every first listing from a seller is reviewed manually. Subsequent listings from sellers in good
  standing are auto-approved with post-hoc screening (prohibited-term scan + report queue).
- Max 10 active listings per seller.
- Listings display: title, category, description, delivery time, revisions, price *indication*
  (fixed / range / "contact for quote"), portfolio media, seller card (rating, completed orders,
  response time, verified badge, member since).

### Order lifecycle
```
requested ──▶ accepted ──▶ in_progress ──▶ delivered ──▶ completed
    │             │             │              │
    └─▶ declined  └─▶cancelled  └─▶cancelled    └─▶ disputed ──▶ resolved_*
```

| Transition | Actor | Rules |
|---|---|---|
| request | buyer | Requirements text, ≤3 reference images. Max 5 open orders per buyer |
| accept / decline | seller | 72h to respond or it auto-expires; accept sets `delivery_due_at` |
| in_progress | seller | Marks work started |
| deliver | seller | Attaches deliverable files (media pipeline) + a note |
| complete | buyer | Or auto-completes 7 days after delivery with no buyer action |
| cancel | either | Before delivery; mutual or unilateral with a reason |
| dispute | buyer | Within 7 days of delivery; freezes auto-complete |

Every transition writes a `marketplace_order_events` row. The order timeline is the evidence record
for any dispute — which matters precisely because money moved off-platform and this is the only
neutral account of what happened.

### Messaging
Order-scoped conversations only ([07](07-database-schema.md) `conversations.subject = order`).
No arbitrary DMs. Messages are moderatable, reportable and retained for the dispute window.
A pre-order "ask a question" thread is allowed against a listing, with a strict rate limit.

### Reviews
- Only on `completed` orders, one per order, within 30 days.
- 1–5 stars plus ≤1000 characters. Sellers may post one public reply.
- Reviews cannot be deleted by the seller; moderators can hide policy-violating reviews with a
  reason.
- Displayed as an average plus the distribution, with the order count — an average alone is
  manipulable.
- Ring detection: reciprocal review pairs and clusters of accounts reviewing only each other are
  flagged by the weekly anomaly job.

### Disputes (Stage 1 — reputational, not financial)
Because the platform holds no money, a dispute cannot award a refund. What it *can* do is record
the outcome on both parties' reputation and sanction bad actors:
```
buyer opens dispute (reason + evidence)
   └─▶ seller responds (7 days)
        └─▶ admin reviews the order timeline, deliverables and messages
             └─▶ outcome: seller_at_fault | buyer_at_fault | inconclusive
                  ├─ seller_at_fault: order marked as such publicly on the seller's record;
                  │  repeated faults ⇒ seller suspension
                  ├─ buyer_at_fault: buyer warned; repeated ⇒ marketplace restriction
                  └─ inconclusive: recorded, no reputation effect
```
This must be stated plainly in the UI: **a dispute affects reputation, it does not recover money.**

## 6. Anti-abuse

| Risk | Control |
|---|---|
| Account-trading listings | Prohibited-term lexicon, manual first-listing review, Critical report reason, immediate seller suspension on confirmation |
| Off-platform payment scams | Mandatory scam-warning interstitial before first order, seller reputation, timeline evidence, permanent bans |
| Fake reviews | Completed-order gate, ring detection, one review per order |
| Seller ghosting | 72h accept window, delivery due dates, response-time metric on the seller card, auto-cancel |
| Buyer ghosting | Auto-complete 7 days after delivery |
| Price gouging / RMT disguised as design work | Category-specific price sanity bands; outliers flagged for review |
| Minors in commercial transactions | Stage 1 has no payments, which sidesteps most of it; a clear statement that under-18 sellers must have guardian consent, and no payouts ever handled by the platform |
| Deliverable copyright theft | Reportable under `stolen_content`; repeat offenders lose seller status |
| Using the marketplace as a DM backdoor | Order-scoped messaging only, rate-limited pre-order questions, full moderation |

## 7. Edge cases

| Case | Behaviour |
|---|---|
| Seller is banned with open orders | Orders auto-cancelled, buyers notified, listings removed, reviews retained |
| Buyer is banned mid-order | Order cancelled, seller notified; if already delivered, the order completes so the seller keeps the review |
| Deliverable media fails processing | Seller notified, delivery not recorded, due date extended by the delay |
| Buyer disputes after auto-completion | Allowed within the 7-day window after auto-complete; after that, report-only |
| Seller deletes a listing with open orders | Listing soft-deletes; open orders continue (orders reference the listing with `ON DELETE RESTRICT`) |
| Both parties stop responding | Order auto-cancels after 30 days of inactivity, no reputation effect |
| Order requirements contain payment details | Allowed (the parties must arrange payment somewhere), but never surfaced publicly and never in notification payloads |
| Dispute where one party claims payment was sent | Recorded as a claim; the platform does not verify off-platform payments and says so |
| Seller's verified CoC account is transferred away | Seller status is unaffected; the account badge on the seller card disappears at the next render |
