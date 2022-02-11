module.exports = {
    root: true,
    env: {
        browser: true,
    },
    parserOptions: {
        parser: '@babel/eslint-parser',
        sourceType: 'module',
        requireConfigFile: false,
    },
    extends: [
        'airbnb/base',
        'plugin:vue/recommended',
    ],
    rules: {
        indent: [
            'error',
            4,
        ],
        'max-len': [
            'error',
            120,
        ],
        'no-console': [
            'error',
            {
                allow: ['error'],
            },
        ],
        'no-unused-vars': [
            'error',
            {
                argsIgnorePattern: '^_',
            },
        ],
        'vue/html-indent': [
            'error',
            4,
        ],
    },
};
