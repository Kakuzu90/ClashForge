import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import pluginVue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'resources/js/types/generated.d.ts', 'resources/js/routes', 'resources/js/actions', 'resources/js/wayfinder'],
    },
    pluginVue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    {
        rules: {
            'vue/no-v-html': 'error',
            'vue/multi-word-component-names': 'off',
        },
    },
    skipFormatting,
);
