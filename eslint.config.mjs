import eslint from "@eslint/js";
import vueEssential from 'eslint-plugin-vue';
import prettierConfig from 'eslint-config-prettier';
import globals from "globals";

export default [
    {
        ignores: [
            'dist/**/*',
            'vendor/**/*',
        ]
    },
    {
        files: ['resources/**/*.{js,vue}'],
    },
    eslint.configs.recommended,
    ...vueEssential.configs['flat/recommended'],
    prettierConfig,
    {
        languageOptions: {
            ecmaVersion: 2018,
            globals: {
                Nova: true,
                ...globals.browser,
                ...globals.node,
            },
        },
        rules: {
            'vue/html-indent': ['error', 2],
            'vue/multi-word-component-names': 'off',
        }
    }
];