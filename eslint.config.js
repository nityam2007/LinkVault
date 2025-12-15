import pluginVue from 'eslint-plugin-vue';

export default [
  ...pluginVue.configs['flat/essential'],
  {
    files: ['resources/js/**/*.{js,vue}'],
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/no-unused-vars': 'warn',
      'vue/valid-template-root': 'error',
      'vue/no-ref-as-operand': 'error',
      'no-undef': 'off',
      'no-unused-vars': 'off',
    },
  },
  {
    ignores: ['node_modules/**', 'public/**', 'vendor/**', 'storage/**'],
  },
];
