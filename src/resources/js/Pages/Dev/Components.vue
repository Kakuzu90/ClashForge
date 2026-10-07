<script setup lang="ts">
import AdminActionPanel from '@/Components/admin/AdminActionPanel.vue';
import AdminAuditTrailList from '@/Components/admin/AdminAuditTrailList.vue';
import AdminDiffViewer from '@/Components/admin/AdminDiffViewer.vue';
import AdminFilterBar from '@/Components/admin/AdminFilterBar.vue';
import AdminPanel from '@/Components/admin/AdminPanel.vue';
import AdminSanctionHistory from '@/Components/admin/AdminSanctionHistory.vue';
import AdminTable, { type AdminColumn } from '@/Components/admin/AdminTable.vue';
import NotificationItem from '@/Components/notifications/NotificationItem.vue';
import SettingsAvatarCropper from '@/Components/settings/SettingsAvatarCropper.vue';
import AccountImageGallery from '@/Components/accounts/AccountImageGallery.vue';
import GameAccountProfile from '@/Components/game/GameAccountProfile.vue';
import GameAsset, { type GameAssetSize } from '@/Components/game/GameAsset.vue';
import GameClanChip from '@/Components/game/GameClanChip.vue';
import GameBaseCard from '@/Components/game/GameBaseCard.vue';
import GamePlayerCard, { type PlayerCardVariant } from '@/Components/game/GamePlayerCard.vue';
import GamePlayerMini from '@/Components/game/GamePlayerMini.vue';
import UiResourceCounter from '@/Components/ui/UiResourceCounter.vue';
import GameProgressionGrid from '@/Components/game/GameProgressionGrid.vue';
import GameThBadge, { type ThBadgeSize } from '@/Components/game/GameThBadge.vue';
import GameFeaturedBadge from '@/Components/game/GameFeaturedBadge.vue';
import GameVerifiedBadge from '@/Components/game/GameVerifiedBadge.vue';
import GameVillageBase from '@/Components/game/GameVillageBase.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiBadge from '@/Components/ui/UiBadge.vue';
import UiButton, { type ButtonSize, type ButtonVariant } from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiCheckbox from '@/Components/ui/UiCheckbox.vue';
import UiDropdownMenu, { type MenuItem } from '@/Components/ui/UiDropdownMenu.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiPill, { type PillTone } from '@/Components/ui/UiPill.vue';
import UiProgress from '@/Components/ui/UiProgress.vue';
import UiRadioGroup, { type RadioOption } from '@/Components/ui/UiRadioGroup.vue';
import UiSelect, { type SelectOption } from '@/Components/ui/UiSelect.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
import UiSteps from '@/Components/ui/UiSteps.vue';
import UiTabs, { type TabItem } from '@/Components/ui/UiTabs.vue';
import UiTextarea from '@/Components/ui/UiTextarea.vue';
import UiToast from '@/Components/ui/UiToast.vue';
import UiToaster from '@/Components/ui/UiToaster.vue';
import UiToggle from '@/Components/ui/UiToggle.vue';
import { useToast } from '@/Composables/useToast';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

// Living gallery of every Ui* variant and state (specs/18 §9). Non-production only. Sample strings
// here describe the component being shown; they are not product copy.
const buttonVariants: ButtonVariant[] = ['primary', 'secondary', 'ghost', 'danger', 'success'];
const buttonSizes: ButtonSize[] = ['sm', 'md', 'lg'];
const pillTones: PillTone[] = ['neutral', 'brand', 'accent', 'success', 'danger', 'warning', 'info'];
const thTones: PillTone[] = ['th-1', 'th-2', 'th-3', 'th-4', 'th-5', 'th-6', 'th-7'];
const avatarSizes = [24, 32, 48, 64, 96, 128] as const;

const text = ref('');
type GameAssetData = App.Domain.GameAssets.Data.GameAssetData;
const assetSizes: GameAssetSize[] = [24, 32, 48, 64];
// An original sample image: the gallery never ships real game art (specs/18 §2).
const sampleImage =
    'data:image/svg+xml,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#f5b800"/><circle cx="32" cy="32" r="14" fill="#1a2140"/></svg>',
    );
const loadedAsset: GameAssetData = { kind: 'unit', url: sampleImage, alt: 'Sample unit', short: 'SU', width: 64, height: 64 };
const fallbackAssets: GameAssetData[] = [
    { kind: 'unit', url: null, alt: 'Archer Queen', short: 'AQ', width: null, height: null },
    { kind: 'town_hall', url: null, alt: 'Town Hall 16', short: '16', width: null, height: null },
    { kind: 'league', url: null, alt: 'Legend League', short: 'LL', width: null, height: null },
    { kind: 'clan_badge', url: null, alt: 'Night Owls clan badge', short: 'NO', width: null, height: null },
];
const brokenAsset: GameAssetData = { kind: 'unit', url: 'data:image/png;base64,broken', alt: 'Barbarian', short: 'B', width: 64, height: 64 };
type PlayerCard = App.Domain.PlayerAccounts.Data.PlayerCardData;
const thLevels = [3, 6, 9, 12, 14, 16, 17, 18];
const thSizes: ThBadgeSize[] = ['sm', 'md', 'lg'];
const cardVariants: PlayerCardVariant[] = ['hero', 'standard', 'compact'];
const sampleClan: App.Domain.PlayerAccounts.Data.AccountClanData = {
    tag: '#2Q8URJ9L',
    name: 'Night Owls',
    level: 22,
    roleLabel: 'Co-leader',
    badge: fallbackAssets[3],
};
const sampleCard: PlayerCard = {
    ulid: '01J0000000000000000000SAMP',
    tag: '#2PQ8GRJC',
    name: 'Sample Chief',
    status: 'verified',
    statusLabel: 'Verified',
    townHallLevel: 16,
    townHall: fallbackAssets[1],
    builderHallLevel: 10,
    xpLevel: 231,
    trophies: 5124,
    bestTrophies: 5524,
    warStars: 1480,
    leagueName: 'Legend League',
    league: fallbackAssets[2],
    clan: sampleClan,
    clanHidden: false,
    featured: true,
    stale: false,
    syncedAt: '2026-10-03T10:00:00+00:00',
    syncedAgeSeconds: 720,
};
const cardStates: { label: string; card: PlayerCard }[] = [
    { label: 'Verified', card: sampleCard },
    {
        label: 'Unverified',
        card: { ...sampleCard, ulid: '01J0000000000000000000UNVF', status: 'unverified', statusLabel: 'Unverified', featured: false },
    },
    {
        label: 'Disputed',
        card: { ...sampleCard, ulid: '01J0000000000000000000DISP', status: 'disputed', statusLabel: 'Under review', featured: false },
    },
    {
        label: 'Stale, clan hidden',
        card: { ...sampleCard, ulid: '01J0000000000000000000STAL', stale: true, syncedAgeSeconds: 259200, clan: null, clanHidden: true },
    },
    {
        label: 'No clan, fields not available',
        card: { ...sampleCard, ulid: '01J0000000000000000000NULL', clan: null, trophies: null, xpLevel: null, league: null, leagueName: null },
    },
];
const sampleEquipment: App.Domain.PlayerAccounts.Data.ProgressionUnitData[] = [
    { name: 'Archer Puppet', asset: loadedAsset, level: 18, maxLevel: 18, maxed: true, locked: false, equipment: [] },
    { name: 'Giant Arrow', asset: brokenAsset, level: 9, maxLevel: 18, maxed: false, locked: false, equipment: [] },
];
const sampleGroup: App.Domain.PlayerAccounts.Data.ProgressionGroupData = {
    key: 'heroes',
    label: 'Heroes',
    village: 'home',
    units: [
        { name: 'Archer Queen', asset: fallbackAssets[0], level: 95, maxLevel: 95, maxed: true, locked: false, equipment: sampleEquipment },
        { name: 'Sample unit', asset: loadedAsset, level: 12, maxLevel: 14, maxed: false, locked: false, equipment: [] },
        { name: 'Barbarian', asset: brokenAsset, level: 7, maxLevel: null, maxed: false, locked: false, equipment: [] },
        { name: 'Locked unit', asset: loadedAsset, level: 0, maxLevel: null, maxed: false, locked: true, equipment: [] },
    ],
};
const sampleTroops: App.Domain.PlayerAccounts.Data.ProgressionGroupData = {
    key: 'troops',
    label: 'Troops',
    village: 'home',
    units: sampleGroup.units.map((unit) => ({ ...unit, equipment: [] })),
};
// Account images (P2-23): ready, processing and failed, for the owner and for a visitor.
const galleryVariant = (name: App.Domain.Media.Enums.VariantName, width: number) => ({ name, url: sampleImage, width, height: width });
const galleryImage = (n: number): App.Domain.PlayerAccounts.Data.AccountImageData => ({
    ulid: `01J00000000000000000000IM${n}`,
    card: galleryVariant('card', 800),
    full: galleryVariant('full', 1600),
    processing: false,
    failed: false,
});
const sampleImageUpload: App.Domain.Media.Data.UploadCollectionData = {
    value: 'account_image',
    label: 'Account image',
    maxBytes: 5 * 1024 * 1024,
    accept: 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',
    typesLabel: 'JPEG, PNG or WebP',
};
const galleryStates = [
    {
        label: 'Owner: ready, processing and failed',
        owner: true,
        images: [
            galleryImage(1),
            galleryImage(2),
            { ulid: '01J00000000000000000000IM3', card: null, full: null, processing: true, failed: false },
            { ulid: '01J00000000000000000000IM4', card: null, full: null, processing: false, failed: true },
        ],
    },
    { label: 'Owner: full (5 of 5)', owner: true, images: [1, 2, 3, 4, 5].map(galleryImage) },
    { label: 'Owner: empty', owner: true, images: [] },
    { label: 'Visitor', owner: false, images: [galleryImage(1), galleryImage(2), galleryImage(3)] },
];

const sampleStats: App.Domain.PlayerAccounts.Data.AccountStatData[] = [
    { key: 'trophies', label: 'Trophies', value: 5124, delta: 142 },
    { key: 'best_trophies', label: 'Best trophies', value: 5310, delta: null },
    { key: 'war_stars', label: 'War stars', value: 1480, delta: 12 },
    { key: 'donations', label: 'Troops donated', value: 104, delta: null },
    { key: 'donations_received', label: 'Troops received', value: 0, delta: null },
];
const townHalls: SelectOption[] = Array.from({ length: 17 }, (_, i) => ({ value: `th${17 - i}`, label: `Town Hall ${17 - i}` }));
const categories: SelectOption[] = [
    { value: 'war', label: 'War', hint: 'Built to stop three-star attacks' },
    { value: 'farming', label: 'Farming', hint: 'Protects storages' },
    { value: 'trophy', label: 'Trophy pushing', hint: 'Protects the Town Hall' },
];
const pickedTownHall = ref<string | null>('th16');
const pickedCategory = ref<string | null>(null);
const bio = ref('');
const selected = ref(false);
const remember = ref(false);
const agreed = ref(true);
const removableShown = ref(true);
const modalOpen = ref(false);
const switchedOn = ref(true);
const switchedOff = ref(false);
const radioOptions: RadioOption[] = [
    { value: 'one', label: 'First option', description: 'A description under the label.' },
    { value: 'two', label: 'Second option', description: 'Arrow keys move between options.' },
    { value: 'three', label: 'Label only' },
];
const radioPick = ref('one');
const sampleTabs: TabItem[] = [
    { key: 'first', label: 'First tab' },
    { key: 'second', label: 'Second tab' },
    { key: 'third', label: 'Third tab' },
];
const underlineTab = ref('first');
const menuItems: MenuItem[] = [
    { key: 'first', label: 'Link item', href: '#h-menu' },
    { key: 'second', label: 'Button item' },
    { key: 'third', label: 'Another button item' },
];
const pillTab = ref('second');
const day = ref('2026-10-01');
interface SampleRow {
    id: number;
    name: string;
    status: string;
}
const tableColumns: AdminColumn[] = [
    { key: 'name', label: 'Name', class: 'w-48' },
    { key: 'status', label: 'Status' },
];
const tableRows: SampleRow[] = [
    { id: 1, name: 'First row', status: 'Open' },
    { id: 2, name: 'Second row', status: 'Closed' },
];
const filterText = ref('');
const sanctionOptions: App.Http.Data.Admin.SanctionFormData = {
    reasons: [
        { value: 'spam', label: 'Spam' },
        { value: 'other', label: 'Other' },
    ],
    maxDays: 90,
    publicReasonMax: 255,
    noteMax: 2000,
};
const sanctionSamples: App.Domain.Moderation.Data.SanctionData[] = [
    {
        typeLabel: 'Suspension',
        reasonLabel: 'Spam',
        publicReason: 'Message shown to the account holder.',
        internalNote: 'Note for staff.',
        issuedBy: 'sample_admin',
        startsAt: '2026-10-01T10:00:00+00:00',
        endsAt: '2026-10-08T10:00:00+00:00',
        state: 'active',
        stateLabel: 'Active',
        liftedBy: null,
        liftedAt: null,
        liftNote: null,
    },
    {
        typeLabel: 'Suspension',
        reasonLabel: 'Other',
        publicReason: 'An earlier message.',
        internalNote: 'An earlier note.',
        issuedBy: 'sample_admin',
        startsAt: '2026-09-01T10:00:00+00:00',
        endsAt: '2026-09-04T10:00:00+00:00',
        state: 'lifted',
        stateLabel: 'Lifted',
        liftedBy: 'sample_admin',
        liftedAt: '2026-09-02T10:00:00+00:00',
        liftNote: 'Why it was lifted.',
    },
];
const trailEntries: App.Http.Data.Admin.AuditTrailEntryData[] = [
    {
        id: 2,
        actionLabel: 'Sample action',
        actorUsername: 'sample_admin',
        actorRoleLabel: 'Admin',
        actorVia: null,
        before: { field: 'old value' },
        after: { field: 'new value' },
        createdAt: '2026-10-01T10:00:00+00:00',
    },
    {
        id: 1,
        actionLabel: 'Sample action',
        actorUsername: null,
        actorRoleLabel: null,
        actorVia: 'console',
        before: null,
        after: { field: 'value' },
        createdAt: '2026-09-30T10:00:00+00:00',
    },
];
const { push } = useToast();

// Specimens report their click, so the gallery also proves each variant's handler fires.
const clicked = (name: string) => push(`${name} clicked`);

const sections = [
    'Buttons',
    'Inputs',
    'Checkboxes',
    'Toggles and radios',
    'Tabs',
    'Dropdown menu',
    'Stat blocks',
    'Cards',
    'Pills',
    'Badges',
    'Avatars',
    'Avatar cropper',
    'Modal',
    'Toasts',
    'Alerts',
    'Skeletons',
    'Progress',
    'Steps',
    'Game assets',
    'Town Hall badges',
    'Player cards',
    'Base cards',
    'Progression grid',
    'Account images',
    'Empty state',
    'Notifications',
    'Admin',
];
// Base cards (P3-03): with a cover, the no-image fallback per tier, no credit, a video.
const baseAuthor: App.Domain.Users.Data.AuthorData = { username: 'ringmaster', displayName: 'Ring Master', avatarUrl: null };
const baseCredit: App.Domain.PlayerAccounts.Data.CreditedAccountData = { ulid: '01jabcdefghjkmnpqrstvwxyz0', name: 'Chief Ana', thLevel: 16 };
const baseCard = (overrides: Partial<App.Domain.Bases.Data.BaseCardData>): App.Domain.Bases.Data.BaseCardData => ({
    ulid: '01jbase000000000000000000a',
    slug: '01jbase000000000000000000a-anti-root-ring',
    title: 'Anti-root ring with a compact core and a very long title that clamps after two lines',
    thLevel: 16,
    category: 'war',
    hasVideo: false,
    cover: null,
    likes: 1240,
    copies: 318,
    views: 20450,
    author: baseAuthor,
    credit: baseCredit,
    ...overrides,
});
const baseCards: { label: string; card: App.Domain.Bases.Data.BaseCardData | null }[] = [
    { label: 'No image, TH 16', card: baseCard({}) },
    { label: 'No image, TH 9, video, no credit', card: baseCard({ ulid: '01jbase000000000000000000b', thLevel: 9, hasVideo: true, credit: null, title: 'Farming box' }) },
    { label: 'No image, TH 4, nothing yet', card: baseCard({ ulid: '01jbase000000000000000000c', thLevel: 4, likes: 0, copies: 0, views: 1, title: 'First base' }) },
    { label: 'Loading', card: null },
];
const notificationSamples: App.Domain.Notifications.Data.NotificationItemData[] = [
    {
        id: '0199a8f0-0000-7000-8000-000000000001',
        category: 'security',
        title: 'New sign-in to your account',
        body: 'From a device we have not seen before: Firefox on Linux, Germany. If it was not you, sign that device out and change your password.',
        hasTarget: true,
        read: false,
        createdAt: '2026-10-01T12:00:00+00:00',
    },
    {
        id: '0199a8f0-0000-7000-8000-000000000002',
        category: 'security',
        title: 'Your suspension is over',
        body: 'It has ended. Your account works as normal again.',
        hasTarget: false,
        read: true,
        createdAt: '2026-09-30T08:00:00+00:00',
    },
];
// Avatar cropper: crops a picked photo, or a generated sample so the gallery needs no file.
const cropFile = ref<File | null>(null);
const cropPicker = ref<HTMLInputElement | null>(null);
const cropResult = ref<{ url: string; label: string } | null>(null);

function pickCropPhoto(event: Event) {
    const input = event.target as HTMLInputElement;
    cropFile.value = input.files?.[0] ?? null;
    input.value = '';
}

function openCropSample() {
    const canvas = document.createElement('canvas');
    canvas.width = 1200;
    canvas.height = 800;
    const context = canvas.getContext('2d');
    if (!context) return;
    const token = (name: string) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    context.fillStyle = token('--bg-surface-raised');
    context.fillRect(0, 0, 1200, 800);
    ['--brand-primary', '--accent', '--state-info'].forEach((name, i) => {
        context.fillStyle = token(name);
        context.beginPath();
        context.arc(300 + i * 300, 400, 160, 0, Math.PI * 2);
        context.fill();
    });
    canvas.toBlob((blob) => blob && (cropFile.value = new File([blob], 'sample.png', { type: 'image/png' })), 'image/png');
}

function onCropSaved(file: File) {
    cropFile.value = null;
    if (cropResult.value) URL.revokeObjectURL(cropResult.value.url);
    cropResult.value = { url: URL.createObjectURL(file), label: `${file.type}, ${Math.round(file.size / 1024)} KB` };
}

const anchor = (name: string) => name.toLowerCase().replace(/\s+/g, '-');
</script>

<template>
    <Head title="Components" />
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-400 focus:rounded-md focus:bg-surface-raised focus:p-2"
    >
        Skip to content
    </a>
    <div class="mx-auto max-w-5xl px-4 py-8">
        <header>
            <p class="text-sm font-semibold text-fg-muted uppercase">Dev only</p>
            <h1 class="font-display text-display">Component gallery</h1>
            <nav aria-label="Sections" class="mt-4 flex flex-wrap gap-2">
                <a
                    v-for="name in sections"
                    :key="name"
                    :href="`#${anchor(name)}`"
                    class="inline-flex min-h-11 items-center rounded-sm px-3 text-sm text-fg-secondary hover:bg-surface-raised hover:text-fg"
                >
                    {{ name }}
                </a>
            </nav>
        </header>

        <main id="main" class="mt-10 flex flex-col gap-12">
            <section :id="anchor('Buttons')" aria-labelledby="h-buttons">
                <h2 id="h-buttons" class="font-display text-h1">Buttons</h2>
                <div v-for="size in buttonSizes" :key="size" class="mt-4 flex flex-wrap items-center gap-3">
                    <UiButton v-for="variant in buttonVariants" :key="variant" :variant="variant" :size="size" @click="clicked(`${variant} ${size}`)">
                        {{ variant }} {{ size }}
                    </UiButton>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <UiButton disabled>Disabled</UiButton>
                    <UiButton loading>Loading</UiButton>
                    <UiButton variant="secondary" icon-only aria-label="Close" @click="clicked('Close')">
                        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </UiButton>
                </div>
                <div class="mt-4 max-w-sm"><UiButton block @click="clicked('Block')">Block</UiButton></div>
            </section>

            <section :id="anchor('Inputs')" aria-labelledby="h-inputs">
                <h2 id="h-inputs" class="font-display text-h1">Inputs</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <UiInput v-model="text" label="Default" hint="Helper text sits under the field." />
                    <UiInput v-model="text" label="With prefix and counter" prefix="#" :maxlength="12" counter />
                    <UiInput v-model="text" label="With suffix" suffix="px" type="number" />
                    <UiInput label="Error" model-value="bad value" error="This value is not valid." />
                    <UiInput label="Disabled" model-value="Cannot edit" disabled />
                    <UiInput label="Read only" model-value="Read only value" readonly />
                    <UiInput v-model="day" label="Date" type="date" hint="Native date picker." />
                    <UiTextarea v-model="bio" label="Textarea with counter" :maxlength="160" counter class="sm:col-span-2" />
                    <UiSelect v-model="pickedCategory" label="Select (native)" :options="categories" placeholder="Choose a category" />
                    <UiSelect
                        v-model="pickedTownHall"
                        label="Select (searchable)"
                        :options="townHalls"
                        searchable
                        hint="Type to filter, arrows to move, Enter to pick."
                    />
                    <UiSelect
                        v-model="pickedCategory"
                        label="Searchable with hints"
                        :options="categories"
                        searchable
                        placeholder="Search categories"
                    />
                    <UiSelect label="Select error" :options="categories" searchable error="Choose a category." />
                    <UiSelect label="Select disabled" :options="categories" model-value="war" searchable disabled />
                </div>
            </section>

            <section :id="anchor('Checkboxes')" aria-labelledby="h-checkboxes">
                <h2 id="h-checkboxes" class="font-display text-h1">Checkboxes</h2>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <UiCheckbox v-model="remember" label="Default" hint="The whole row is the touch target." />
                    <UiCheckbox v-model="agreed" label="Checked" />
                    <UiCheckbox :model-value="false" indeterminate label="Indeterminate" />
                    <UiCheckbox :model-value="false" label="Error" error="Tick this to continue." />
                    <UiCheckbox :model-value="true" disabled label="Disabled" />
                </div>
            </section>

            <section :id="anchor('Toggles and radios')" aria-labelledby="h-toggles">
                <h2 id="h-toggles" class="font-display text-h1">Toggles and radios</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <UiToggle v-model="switchedOn" label="On" hint="A switch is a checkbox with role switch." />
                        <UiToggle v-model="switchedOff" label="Off" />
                        <UiToggle :model-value="false" label="Error" error="This setting could not be saved." />
                        <UiToggle :model-value="true" disabled label="Disabled" />
                    </div>
                    <div class="flex flex-col gap-6">
                        <UiRadioGroup v-model="radioPick" legend="Radio group" :options="radioOptions" />
                        <UiRadioGroup model-value="two" legend="Radio group, disabled" :options="radioOptions" disabled />
                        <UiRadioGroup legend="Radio group, error" :options="radioOptions" error="Choose one option." />
                    </div>
                </div>
            </section>

            <section :id="anchor('Tabs')" aria-labelledby="h-tabs">
                <h2 id="h-tabs" class="font-display text-h1">Tabs</h2>
                <div class="mt-4 flex flex-col gap-8">
                    <UiTabs v-model="underlineTab" :tabs="sampleTabs" label="Underline tabs">
                        <template v-for="t in sampleTabs" #[t.key] :key="t.key">
                            <p class="text-body text-fg-secondary">{{ t.label }} panel (underline). Arrow keys, Home and End move between tabs.</p>
                        </template>
                    </UiTabs>
                    <UiTabs v-model="pillTab" :tabs="sampleTabs" label="Pill tabs" variant="pill">
                        <template v-for="t in sampleTabs" #[t.key] :key="t.key">
                            <p class="text-body text-fg-secondary">{{ t.label }} panel (pill).</p>
                        </template>
                    </UiTabs>
                </div>
            </section>

            <section :id="anchor('Dropdown menu')" aria-labelledby="h-menu">
                <h2 id="h-menu" class="font-display text-h1">Dropdown menu</h2>
                <p class="mt-2 max-w-prose text-body text-fg-secondary">
                    Enter, Space or Down opens on the first item, Up on the last. Arrows, Home and End move; Escape closes.
                </p>
                <div class="mt-4 flex gap-8">
                    <UiDropdownMenu :items="menuItems" label="Sample menu, aligned start" align="start" @select="clicked">
                        <template #trigger><span class="px-2 text-fg">Open (start)</span></template>
                    </UiDropdownMenu>
                    <UiDropdownMenu :items="menuItems" label="Sample menu, aligned end" @select="clicked">
                        <template #trigger><span class="px-2 text-fg">Open (end)</span></template>
                    </UiDropdownMenu>
                </div>
            </section>

            <section :id="anchor('Stat blocks')" aria-labelledby="h-stats">
                <h2 id="h-stats" class="font-display text-h1">Stat blocks</h2>
                <dl class="mt-4 grid grid-cols-3 gap-4 rounded-lg border border-line bg-surface p-4">
                    <UiStatBlock :value="0" label="Zero" />
                    <UiStatBlock :value="3400" label="Thousands" />
                    <UiStatBlock :value="1250000" label="Millions" />
                    <UiStatBlock :value="5124" :delta="142" delta-label="Change over the last 7 days" label="Rising" />
                    <UiStatBlock :value="1480" :delta="-30" delta-label="Change over the last 7 days" label="Falling" />
                    <UiStatBlock :value="null" label="Not available" />
                </dl>
            </section>

            <section :id="anchor('Cards')" aria-labelledby="h-cards">
                <h2 id="h-cards" class="font-display text-h1">Cards</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <UiCard variant="flat" class="p-4">Flat</UiCard>
                    <UiCard variant="raised" class="p-4">Raised</UiCard>
                    <UiCard variant="interactive" class="p-4">
                        <a href="#cards" class="outline-none">Interactive (hover me)</a>
                    </UiCard>
                    <UiCard variant="feature" class="p-4">Feature</UiCard>
                    <UiCard variant="raised" selected class="p-4">Selected</UiCard>
                </div>
            </section>

            <section :id="anchor('Pills')" aria-labelledby="h-pills">
                <h2 id="h-pills" class="font-display text-h1">Pills</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    <UiPill v-for="tone in pillTones" :key="tone" :label="tone" :tone="tone" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <UiPill v-for="(tone, i) in thTones" :key="tone" :label="`TH tier ${i + 1}`" :tone="tone" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <UiPill label="Selectable" selectable :selected="selected" @toggle="selected = !selected" />
                    <UiPill v-if="removableShown" label="Removable" removable @remove="removableShown = false" />
                    <UiButton v-else size="sm" variant="ghost" @click="removableShown = true">Show the removable pill again</UiButton>
                </div>
            </section>

            <section :id="anchor('Badges')" aria-labelledby="h-badges">
                <h2 id="h-badges" class="font-display text-h1">Badges</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    <UiBadge kind="verified" />
                    <UiBadge kind="featured" />
                    <UiBadge kind="role" role="mod" />
                    <UiBadge kind="role" role="admin" />
                    <UiBadge kind="rarity" rarity="common" />
                    <UiBadge kind="rarity" rarity="rare" />
                    <UiBadge kind="rarity" rarity="epic" />
                    <UiBadge kind="rarity" rarity="legendary" />
                </div>
            </section>

            <section :id="anchor('Avatars')" aria-labelledby="h-avatars">
                <h2 id="h-avatars" class="font-display text-h1">Avatars</h2>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <UiAvatar v-for="size in avatarSizes" :key="size" :size="size" name="Sample Player" />
                </div>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <UiAvatar name="Verified Player" verified :size="64" />
                    <UiAvatar name="Broken Image" src="/missing-avatar.png" :size="64" />
                    <UiAvatar name="Loading" loading loading-label="Loading avatar" :size="64" />
                </div>
            </section>

            <section :id="anchor('Avatar cropper')" aria-labelledby="h-avatar-cropper">
                <h2 id="h-avatar-cropper" class="font-display text-h1">Avatar cropper</h2>
                <p class="mt-2 text-sm text-fg-secondary">
                    Opens on a picked file. Drag, pinch, scroll or use arrow keys and +/- to frame; Save emits the cropped square.
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <input
                        ref="cropPicker"
                        type="file"
                        class="sr-only"
                        tabindex="-1"
                        aria-hidden="true"
                        accept="image/jpeg,image/png,image/webp"
                        @change="pickCropPhoto"
                    />
                    <UiButton variant="secondary" @click="cropPicker?.click()">Crop a photo</UiButton>
                    <UiButton variant="ghost" @click="openCropSample">Crop a sample image</UiButton>
                </div>
                <div v-if="cropResult" class="mt-4 flex items-center gap-4">
                    <UiAvatar name="Cropped result" :src="cropResult.url" :size="128" />
                    <p class="text-sm text-fg-secondary">{{ cropResult.label }}</p>
                </div>
                <SettingsAvatarCropper
                    :file="cropFile"
                    accept="image/jpeg,image/png,image/webp"
                    :max-bytes="2 * 1024 * 1024"
                    :output-size="512"
                    :min-size="200"
                    @save="onCropSaved"
                    @cancel="cropFile = null"
                />
            </section>

            <section :id="anchor('Modal')" aria-labelledby="h-modal">
                <h2 id="h-modal" class="font-display text-h1">Modal / sheet</h2>
                <p class="mt-2 text-sm text-fg-secondary">
                    Bottom sheet below 640px, centred dialog above. Esc closes; focus returns to the trigger.
                </p>
                <div class="mt-4"><UiButton variant="secondary" @click="modalOpen = true">Open modal</UiButton></div>
                <UiModal v-model:open="modalOpen" title="Modal title" description="Supporting description for the dialog.">
                    <UiInput v-model="text" label="Field inside the modal" />
                    <template #footer="{ close }">
                        <UiButton variant="ghost" @click="close">Cancel</UiButton>
                        <UiButton @click="close">Confirm</UiButton>
                    </template>
                </UiModal>
            </section>

            <section :id="anchor('Toasts')" aria-labelledby="h-toasts">
                <h2 id="h-toasts" class="font-display text-h1">Toasts</h2>
                <div class="mt-4 grid max-w-md gap-3">
                    <UiToast kind="info" title="Info toast" body="Neutral information." />
                    <UiToast kind="success" title="Success toast" body="The action completed." />
                    <UiToast kind="danger" title="Danger toast" body="Stays until dismissed." />
                    <UiToast kind="reward" title="Reward toast" body="Gold accent with the reward entrance." />
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <UiButton size="sm" variant="secondary" @click="push('Info toast')">Push info</UiButton>
                    <UiButton size="sm" variant="secondary" @click="push('Saved', { kind: 'success' })">Push success</UiButton>
                    <UiButton size="sm" variant="secondary" @click="push('Something failed', { kind: 'danger' })">Push danger</UiButton>
                    <UiButton size="sm" variant="secondary" @click="push('Reward unlocked', { kind: 'reward' })">Push reward</UiButton>
                </div>
            </section>

            <section :id="anchor('Alerts')" aria-labelledby="h-alerts">
                <h2 id="h-alerts" class="font-display text-h1">Alerts</h2>
                <p class="mt-2 text-sm text-fg-secondary">In-flow messages that stay on the page. Danger uses role alert, the rest role status.</p>
                <div class="mt-4 flex max-w-2xl flex-col gap-3">
                    <UiAlert kind="info">Info alert with body text only.</UiAlert>
                    <UiAlert kind="success" title="Success alert">With a title and supporting text.</UiAlert>
                    <UiAlert kind="warning" title="Warning alert">Something needs attention soon.</UiAlert>
                    <UiAlert kind="danger" title="Danger alert">Something went wrong and needs action.</UiAlert>
                    <UiAlert kind="maintenance" title="Maintenance alert" dismissible @dismiss="push('Dismissed', { kind: 'info' })">
                        A service we depend on is down. Dismissible.
                    </UiAlert>
                </div>
            </section>

            <section :id="anchor('Skeletons')" aria-labelledby="h-skeletons">
                <h2 id="h-skeletons" class="font-display text-h1">Skeletons</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <UiSkeleton variant="text" label="Loading text" />
                    <UiSkeleton variant="avatar" />
                    <UiSkeleton variant="stat" />
                    <UiSkeleton variant="media" />
                    <UiSkeleton variant="card" />
                </div>
            </section>

            <section :id="anchor('Progress')" aria-labelledby="h-progress">
                <h2 id="h-progress" class="font-display text-h1">Progress</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-2">
                    <UiProgress label="Uploading screenshot.png" :value="0" />
                    <UiProgress label="Uploading screenshot.png" :value="64" />
                    <UiProgress label="Uploading screenshot.png" :value="100" />
                    <UiProgress label="Processing (indeterminate)" />
                    <UiProgress label="Hidden label, still announced" :value="40" hide-label />
                </div>
            </section>

            <section :id="anchor('Steps')" aria-labelledby="h-steps">
                <h2 id="h-steps" class="font-display text-h1">Steps</h2>
                <div class="mt-4 flex flex-col gap-6">
                    <UiSteps :steps="['Find your account', 'Prove it is yours', 'Done']" :current="1" label="First step" />
                    <UiSteps :steps="['Find your account', 'Prove it is yours', 'Done']" :current="2" label="Middle step" />
                    <UiSteps :steps="['Find your account', 'Prove it is yours', 'Done']" :current="3" label="Last step" />
                </div>
            </section>

            <section :id="anchor('Game assets')" aria-labelledby="h-game-assets">
                <h2 id="h-game-assets" class="font-display text-h1">Game assets</h2>
                <p class="mt-2 max-w-prose text-body text-fg-secondary">
                    Placeholders appear when the category is switched off, no pack is active, the entry is unknown or the image fails. The loaded
                    state uses an original sample image.
                </p>
                <div class="mt-4 flex flex-col gap-6">
                    <div v-for="asset in [loadedAsset, ...fallbackAssets, brokenAsset]" :key="asset.alt" class="flex items-end gap-4">
                        <GameAsset v-for="s in assetSizes" :key="s" :asset="asset" :size="s" />
                        <span class="text-sm text-fg-secondary"
                            >{{ asset.alt }} ({{ asset.url === null ? 'fallback' : asset.alt === 'Barbarian' ? 'missing image' : 'loaded' }})</span
                        >
                    </div>
                </div>
            </section>

            <section :id="anchor('Town Hall badges')" aria-labelledby="h-th-badges">
                <h2 id="h-th-badges" class="font-display text-h1">Town Hall badges</h2>
                <p class="mt-2 max-w-prose text-body text-fg-secondary">
                    One tier colour per range, the numeral always visible; 17 and above get the gold ring. The large size shows the Town Hall image
                    when one is resolved. Also: the verified badge and the clan chip.
                </p>
                <div class="mt-4 flex flex-col gap-4">
                    <div v-for="size in thSizes" :key="size" class="flex flex-wrap items-center gap-3">
                        <GameThBadge v-for="level in thLevels" :key="level" :level="level" :size="size" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <GameThBadge :level="16" size="lg" :asset="loadedAsset" />
                        <GameThBadge :level="10" size="md" builder />
                        <GameVerifiedBadge :size="16" />
                        <GameVerifiedBadge :size="24" />
                        <GameFeaturedBadge :size="16" />
                        <GameFeaturedBadge :size="24" />
                        <GameVerifiedBadge :size="24" label="Verified player: owns a Clash of Clans account proven with the in-game API token" />
                        <GameClanChip :clan="sampleClan" />
                        <GameClanChip :clan="{ ...sampleClan, roleLabel: null, level: null }" />
                    </div>
                </div>
            </section>

            <section :id="anchor('Base cards')" aria-labelledby="h-base-cards">
                <h2 id="h-base-cards" class="font-display text-h1">Base cards</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="sample in baseCards" :key="sample.label" class="flex flex-col gap-1">
                        <span class="text-xs text-fg-muted uppercase">{{ sample.label }}</span>
                        <GameBaseCard :card="sample.card" category-label="War" />
                    </div>
                </div>
                <h3 class="mt-6 text-h3 text-fg">Mini player card</h3>
                <div class="mt-2 flex flex-col gap-3">
                    <GamePlayerMini :author="baseAuthor" :credit="baseCredit" />
                    <GamePlayerMini :author="{ ...baseAuthor, displayName: null }" :credit="null" />
                </div>
                <h3 class="mt-6 text-h3 text-fg">Resource counters</h3>
                <div class="mt-2 flex flex-wrap gap-4">
                    <UiResourceCounter kind="likes" :count="1" />
                    <UiResourceCounter kind="copies" :count="318" />
                    <UiResourceCounter kind="views" :count="20450" />
                    <UiResourceCounter kind="comments" :count="12" />
                </div>
            </section>

            <section :id="anchor('Player cards')" aria-labelledby="h-player-cards">
                <h2 id="h-player-cards" class="font-display text-h1">Player cards</h2>
                <div class="mt-4 flex flex-col gap-6">
                    <div v-for="variant in cardVariants" :key="variant" class="flex flex-col gap-3">
                        <h3 class="text-h3 text-fg">{{ variant }}</h3>
                        <div class="grid gap-4" :class="variant === 'hero' ? '' : 'sm:grid-cols-2'">
                            <div v-for="state in cardStates" :key="state.label" class="flex flex-col gap-1">
                                <span class="text-xs text-fg-muted uppercase">{{ state.label }}</span>
                                <GamePlayerCard :card="state.card" :variant="variant" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-xs text-fg-muted uppercase">Loading</span>
                                <GamePlayerCard :card="null" :variant="variant" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section :id="anchor('Progression grid')" aria-labelledby="h-progression">
                <h2 id="h-progression" class="font-display text-h1">Progression grid</h2>
                <div class="mt-4">
                    <GameProgressionGrid :group="sampleGroup" />
                </div>
                <h3 class="mt-6 font-display text-h3">Account profile</h3>
                <div class="mt-3">
                    <GameAccountProfile :card="sampleCard" :stats="sampleStats" delta-label="Change over the last 7 days" />
                </div>
                <h3 class="mt-6 font-display text-h3">Featured account on a user profile (linked, no donations)</h3>
                <div class="mt-3">
                    <GameAccountProfile :card="sampleCard" :donations="false" linked label="Featured account" />
                </div>
                <h3 class="mt-6 font-display text-h3">Village base, loaded and loading</h3>
                <div class="mt-3 flex flex-col gap-4">
                    <GameVillageBase :hall="fallbackAssets[1]" :groups="[sampleGroup, sampleTroops]" :side="['heroes']" />
                    <GameVillageBase :hall="fallbackAssets[1]" :groups="null" :side="['heroes']" />
                </div>
            </section>

            <section :id="anchor('Account images')" aria-labelledby="h-account-images" class="flex flex-col gap-6">
                <h2 id="h-account-images" class="font-display text-h1">Account images</h2>
                <div v-for="state in galleryStates" :key="state.label" class="flex flex-col gap-2">
                    <span class="text-xs text-fg-muted uppercase">{{ state.label }}</span>
                    <AccountImageGallery
                        account-ulid="01J0000000000000000000CARD"
                        account-name="Fixture Chief"
                        :images="state.images"
                        :can-manage="state.owner"
                        :max="5"
                        :upload="state.owner ? sampleImageUpload : null"
                    />
                </div>
            </section>

            <section :id="anchor('Empty state')" aria-labelledby="h-empty">
                <h2 id="h-empty" class="font-display text-h1">Empty state</h2>
                <UiCard variant="flat" class="mt-4">
                    <UiEmptyState title="Nothing here yet" body="Explains why the list is empty and what to do next.">
                        <template #illustration>
                            <svg width="96" height="72" viewBox="0 0 96 72">
                                <rect
                                    x="8"
                                    y="16"
                                    width="56"
                                    height="40"
                                    rx="8"
                                    fill="var(--bg-surface-raised)"
                                    stroke="var(--border-strong)"
                                    stroke-width="2"
                                />
                                <circle cx="72" cy="24" r="14" fill="var(--brand-primary)" opacity="0.9" />
                                <rect x="20" y="30" width="28" height="4" rx="2" fill="var(--border-strong)" />
                                <rect x="20" y="40" width="18" height="4" rx="2" fill="var(--border-strong)" />
                            </svg>
                        </template>
                        <template #action>
                            <UiButton @click="clicked('Primary action')">Primary action</UiButton>
                        </template>
                    </UiEmptyState>
                </UiCard>
            </section>
            <section :id="anchor('Notifications')" aria-labelledby="h-notifications">
                <h2 id="h-notifications" class="font-display text-h1">Notifications</h2>
                <p class="mt-2 text-sm text-fg-secondary">Notification centre rows: unread with the gold border and a New label, then read.</p>
                <ol class="mt-4 flex max-w-2xl flex-col divide-y divide-line-subtle overflow-hidden rounded-lg border border-line bg-surface">
                    <li v-for="sample in notificationSamples" :key="sample.id"><NotificationItem :notification="sample" /></li>
                </ol>
            </section>
            <section :id="anchor('Admin')" aria-labelledby="h-admin" class="font-body">
                <h2 id="h-admin" class="font-display text-h1">Admin</h2>
                <p class="mt-2 text-sm text-fg-secondary">Plain on purpose: body font, small radius, no lift (specs/18 §4).</p>
                <div class="mt-4 flex flex-col gap-6">
                    <AdminFilterBar label="Sample filters" can-clear @apply="clicked('Apply')" @clear="clicked('Clear')">
                        <UiInput v-model="filterText" label="Filter field" />
                    </AdminFilterBar>
                    <AdminTable
                        :columns="tableColumns"
                        :rows="tableRows"
                        :row-key="(row: SampleRow) => row.id"
                        caption="Table with expandable rows"
                        expandable
                    >
                        <template #detail="{ row }">
                            <p class="text-sm text-fg-secondary">Detail for {{ row.name }}.</p>
                        </template>
                    </AdminTable>
                    <AdminTable :columns="tableColumns" :rows="[]" :row-key="() => 0" caption="Table loading" loading :skeleton-rows="2" />
                    <AdminTable :columns="tableColumns" :rows="[]" :row-key="() => 0" caption="Table empty">
                        <template #empty>No rows match.</template>
                    </AdminTable>
                    <AdminActionPanel
                        ulid="01hzzzzzzzzzzzzzzzzzzzzzzz"
                        username="sample_user"
                        :abilities="{ suspend: true, ban: true, lift: true, activeType: 'suspension' }"
                        :options="sanctionOptions"
                    />
                    <AdminActionPanel
                        ulid="01hzzzzzzzzzzzzzzzzzzzzzzz"
                        username="sample_user"
                        :abilities="{ suspend: false, ban: false, lift: false, activeType: null }"
                        :options="sanctionOptions"
                    />
                    <AdminPanel title="Panel" description="A dashboard panel with a one-line description.">
                        <dl class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Last hour</dt>
                                <dd class="text-h3 text-fg tabular-nums">12</dd>
                            </div>
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Last 24 hours</dt>
                                <dd class="text-h3 text-fg tabular-nums">1,204</dd>
                            </div>
                        </dl>
                    </AdminPanel>
                    <AdminSanctionHistory :sanctions="sanctionSamples" />
                    <AdminSanctionHistory :sanctions="[]" />
                    <AdminAuditTrailList :entries="trailEntries" />
                    <AdminAuditTrailList :entries="[]" />
                    <AdminDiffViewer
                        :before="{ role: 'user', note: 'kept', removed: true }"
                        :after="{ role: 'moderator', note: 'kept', added: [1, 2] }"
                    />
                </div>
            </section>
        </main>
    </div>
    <UiToaster />
</template>
