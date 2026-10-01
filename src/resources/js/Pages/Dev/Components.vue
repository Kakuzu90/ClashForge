<script setup lang="ts">
import GameAsset, { type GameAssetSize } from '@/Components/game/GameAsset.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiBadge from '@/Components/ui/UiBadge.vue';
import UiButton, { type ButtonSize, type ButtonVariant } from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiCheckbox from '@/Components/ui/UiCheckbox.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiPill, { type PillTone } from '@/Components/ui/UiPill.vue';
import UiProgress from '@/Components/ui/UiProgress.vue';
import UiRadioGroup, { type RadioOption } from '@/Components/ui/UiRadioGroup.vue';
import UiSelect, { type SelectOption } from '@/Components/ui/UiSelect.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
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
const pillTab = ref('second');
const { push } = useToast();

// Specimens report their click, so the gallery also proves each variant's handler fires.
const clicked = (name: string) => push(`${name} clicked`);

const sections = [
    'Buttons',
    'Inputs',
    'Checkboxes',
    'Toggles and radios',
    'Tabs',
    'Stat blocks',
    'Cards',
    'Pills',
    'Badges',
    'Avatars',
    'Modal',
    'Toasts',
    'Alerts',
    'Skeletons',
    'Progress',
    'Game assets',
    'Empty state',
];
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

            <section :id="anchor('Stat blocks')" aria-labelledby="h-stats">
                <h2 id="h-stats" class="font-display text-h1">Stat blocks</h2>
                <dl class="mt-4 grid grid-cols-3 gap-4 rounded-lg border border-line bg-surface p-4">
                    <UiStatBlock :value="0" label="Zero" />
                    <UiStatBlock :value="3400" label="Thousands" />
                    <UiStatBlock :value="1250000" label="Millions" />
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
        </main>
    </div>
    <UiToaster />
</template>
