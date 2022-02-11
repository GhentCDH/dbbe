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
        'no-restricted-syntax': [
            'error',
            {
                selector: 'ForInStatement',
                // eslint-disable-next-line max-len
                message: 'for..in loops iterate over the entire prototype chain, which is virtually never what you want. Use Object.{keys,values,entries}, and iterate over the resulting array.',
            },
            {
                selector: 'LabeledStatement',
                // eslint-disable-next-line max-len
                message: 'Labels are a form of GOTO; using them makes code confusing and hard to maintain and understand.',
            },
            {
                selector: 'WithStatement',
                // eslint-disable-next-line max-len
                message: '`with` is disallowed in strict mode because it makes code impossible to predict and optimize.',
            },
        ],
        'vue/html-indent': [
            'error',
            4,
        ],
    },
};
