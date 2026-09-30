<script setup lang="ts">
// Supercell Fan Content Policy disclaimer, verbatim, on every layout incl. admin, never dismissible
// (specs/18 §2.1 condition 6, specs/03 NFR-PRIV-5).
const FAN_CONTENT_POLICY_URL = 'https://supercell.com/en/fan-content-policy/';
</script>

<template>
    <footer class="mt-10 border-t border-line-subtle">
        <div class="gutter-x mx-auto flex w-full max-w-[1200px] flex-col gap-2 py-6">
            <p class="font-display text-body text-fg-secondary">Clash Commons</p>
            <p class="text-sm text-fg-secondary">
                This material is unofficial and is not endorsed by Supercell. For more information see
                <a :href="FAN_CONTENT_POLICY_URL" class="text-fg underline underline-offset-2 hover:text-brand" rel="noopener"
                    >Supercell's Fan Content Policy</a
                >.
            </p>
        </div>
    </footer>
</template>
