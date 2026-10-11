import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';
import tseslint from 'typescript-eslint';
import globals from 'globals';

export default tseslint.config(
  { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/cache/**', 'qa-kit/**', 'playwright-report/**', 'test-results/**'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
  },
  {
    files: ['resources/js/**/*.vue'],
    languageOptions: {
      parser: vueParser,
      parserOptions: { sourceType: 'module', ecmaVersion: 'latest' },
      globals: globals.browser,
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      '@typescript-eslint/no-unused-vars': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/html-self-closing': 'off',
      'vue/attributes-order': 'off',
      'vue/attribute-hyphenation': 'off',
    },
  },
  {
    files: ['resources/js/**/*.{js,vue}'],
    rules: {
      'no-console': ['error', { allow: ['warn', 'error'] }],
      'no-undef': 'off',
      '@typescript-eslint/no-unused-vars': 'off',
    },
  },
);
