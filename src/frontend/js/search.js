 /* global Vue, VueFormGenerator, VueTables */

Vue.use(VueTables.ServerTable);

var app = new Vue({ // eslint-disable-line no-unused-vars
    el: '#app',
    delimiters: ['${', '}'],
    components: {
        'vue-form-generator': VueFormGenerator.component
    },
    data: function () {
        return {
            model: {},
            schema: {
                fields: [
                    // TODO: get the field defenitions using AJAX
                    {
                        // TODO: apply filter on enter
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        model: 'name',
                        placeholder: 'Manuscript name',
                        validator: VueFormGenerator.validators.string
                    }
                ]
            },
            formOptions: {
                validateAfterLoad: true,
                validateAfterChanged: true
            },
            options: {
                'filterable': false,
                'orderBy': {
                    'column': 'name'
                },
                'perPage': 25,
                'perPageValues': [25, 50, 100],
                'sortable': ['name', 'date'],
                customFilters: ['filters']
            }
        };
    },
    methods: {
        applyFilters: function () {
            VueTables.Event.$emit('vue-tables.filter::filters', this.model);
        }
    }
});
