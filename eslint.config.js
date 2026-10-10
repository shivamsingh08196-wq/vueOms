import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/cache/**', 'qa-kit/e2e/test-results/**', 'qa-kit/e2e/playwright-report/**'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...vue.configs['flat/recommended'],
  { files: ['resources/js/**/*.vue'], languageOptions: { parser: vueParser, parserOptions: { sourceType: 'module', ecmaVersion: 'latest' } } },
  { files: ['resources/js/**/*.{js,vue}'], rules: { 'no-console': ['error', { allow: ['warn', 'error'] }] } },
);
