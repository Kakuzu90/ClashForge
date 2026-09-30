import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import pluginVue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    {
        ignores: ['vendor', 'node_modules', '.vite', 'public', 'bootstrap/ssr', 'resources/js/types/generated.d.ts', 'resources/js/routes', 'resources/js/actions', 'resources/js/wayfinder'],
    },
    pluginVue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    {
        rules: {
            'vue/no-v-html': 'error',
            'vue/multi-word-component-names': 'off',
            // Optional TS props are intentionally undefined; defaults are declared only where they matter.
            'vue/require-default-prop': 'off',
            '@typescript-eslint/no-unused-vars': ['error', { varsIgnorePattern: '^_', argsIgnorePattern: '^_', ignoreRestSiblings: true }],
            // Server state goes through Inertia; only the upload composable talks to app routes directly (specs/19 §2).
            'no-restricted-globals': [
                'error',
                { name: 'fetch', message: 'Use Inertia router/useForm; direct requests live only in useUpload.' },
                { name: 'XMLHttpRequest', message: 'Use Inertia router/useForm; direct requests live only in useUpload.' },
            ],
            'no-restricted-imports': ['error', { paths: [{ name: 'axios', message: 'Use Inertia router/useForm; direct requests live only in useUpload.' }] }],
        },
    },
    {
        files: ['resources/js/Composables/useUpload.ts', 'tests/js/**'],
        rules: { 'no-restricted-globals': 'off' },
    },
    skipFormatting,
);
