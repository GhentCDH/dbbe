<template>
    <div>
        <aside class="col-sm-3">
            <div class="bg-tertiary">
                <div class="padding-default">
                    <vue-form-generator :schema="schema" :model="model" :options="formOptions" @model-updated="filterChanged"></vue-form-generator>
                </div>
            </div>
        </aside>
        <article class="col-sm-9">
            <h2>Search Manuscripts</h2>
            <v-server-table
                ref="resultTable"
                url="/manuscripts/search_api"
                :columns="['name', 'date', 'content']"
                :options="tableOptions">
                <a slot="name" slot-scope="props" :href="'/manuscrips/' + props.row.id">
                    {{ formatName(props.row) }}
                </a>
                <template slot="date" slot-scope="props" v-if="props.row.date_floor_year && props.row.date_ceiling_year">
                    <template v-if="props.row.date_floor_year === props.row.date_ceiling_year">
                        {{ props.row.date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.date_floor_year }} - {{ props.row.date_ceiling_year }}
                    </template>
                </template>
                <template slot="content" slot-scope="props" v-if="props.row.content">
                    <template v-for="displayContent in filterDisplayContent(props.row.content)">
                        <ul v-if="displayContent.length > 1">
                            <li v-for="content in displayContent">{{ content.name }}</li>
                        </ul>
                        <template v-else>
                            {{ displayContent[0].name}}
                        </template>
                    </template>
                </template>
            </v-server-table>
        </article>
    </div>
</template>
<script>
    window.axios = require('axios')
    window.noUiSlider = require('nouislider')

    import Vue from 'vue'
    import VueFormGenerator from 'vue-form-generator'
    import VueMultiselect from 'vue-multiselect'
    import VueTables from 'vue-tables-2'
    import wNumb from 'wnumb'

    import fieldMultiselectClear from './components/formfields/fieldMultiselectClear'

    Vue.use(VueFormGenerator)
    Vue.use(VueTables.ServerTable)

    Vue.component('multiselect', VueMultiselect)
    Vue.component('fieldMultiselectClear', fieldMultiselectClear)

    export default {
        data() {
            return {
                model: {},
                schema: {
                    fields: {
                        city: this.createMultiSelect('City'),
                        library: this.createMultiSelect('Library', {dependency: 'city'}),
                        fund: this.createMultiSelect('Fund', {dependency: 'library'}),
                        shelf: {
                            type: 'input',
                            intputType: 'text',
                            label: 'Shelf Number',
                            model: 'shelf'
                        },
                        date: {
                            type: 'noUiSlider',
                            label: 'Date',
                            model: 'date',
                            min: 0,
                            max: 2018,
                            step: 10,
                            noUiSliderOptions: {
                                behaviour: 'drag',
                                connect: true,
                                start: [501, 1500],
                                step: 1,
                                tooltips: [ wNumb({ decimals: 0 }), wNumb({ decimals: 0 }) ]
                            }
                        },
                        content: this.createMultiSelect('Content'),
                        patron: this.createMultiSelect('Patron'),
                        scribe: this.createMultiSelect('Scribe'),
                        origin: this.createMultiSelect('Origin')
                    }
                },
                formOptions: {
                    validateAfterLoad: true,
                    validateAfterChanged: true
                },
                tableOptions: {
                    'filterable': false,
                    'orderBy': {
                        'column': 'name'
                    },
                    'perPage': 25,
                    'perPageValues': [25, 50, 100],
                    'sortable': ['name', 'date'],
                    customFilters: ['filters']
                },
                oldOrder: {},
                openRequests: 0,
                cancel: null
            }
        },
        mounted () {
            this.$nextTick( () => {
                this.setFilters()
            })
        },
        methods: {
            formatName(row) {
                let result = ''
                result += row.city.name.toUpperCase()
                if (row.library) {
                    result += ' - ' +  row.library.name
                }
                if (row.fund) {
                    result += ' - ' +  row.fund.name
                }
                if (row.shelf) {
                    result += ' ' +  row.shelf
                }
                return result
            },
            filterDisplayContent(contentList) {
                // extra dimension is needed to declare variable in template using v-for
                let result = [[]]
                for (let contentItem of contentList) {
                    if (contentItem.display) {
                        result[0].push(contentItem)
                    }
                }
                return result
            },
            createMultiSelect(label, extra) {
                let result = {
                    type: 'multiselectClear',
                    label: label,
                    placeholder: 'Loading',
                    model: label.toLowerCase(),
                    // Values will be loaded using ajax request
                    values: [],
                    selectOptions: {
                        customLabel: ({id, name}) => {
                            return name
                        },
                        showLabels: false,
                        loading: true
                    },
                    // Will be enabled when list of scribes is loaded
                    disabled: true
                }
                if (extra !== undefined) {
                    for (let key of Object.keys(extra)) {
                        result[key] = extra[key]
                    }
                }
                return result
            },
            cleanFilterValues() {
                let result = {}
                if (this.model !== undefined) {
                    for (let fieldName of Object.keys(this.model)) {
                        if (this.model[fieldName] === null || this.model[fieldName].derived) {
                            continue
                        }
                        if (this.schema.fields[fieldName].type == 'multiselectClear') {
                            result[fieldName] = this.model[fieldName]['id']
                        }
                        else {
                            result[fieldName] = this.model[fieldName]
                        }
                    }
                }
                return result
            },
            setFilters() {
                if (this.openRequests > 0) {
                    this.cancel('Operation canceled by newer request')
                }
                for (let fieldName of Object.keys(this.schema.fields)) {
                    if (this.schema.fields[fieldName].type == 'multiselectClear') {
                        if (this.model[fieldName] && this.model[fieldName].derived) {
                            this.model[fieldName] = null
                        }
                        if (this.model[fieldName] && this.schema.fields[fieldName].dependency && !this.model[this.schema.fields[fieldName].dependency]) {
                            this.model[fieldName] = null
                        }
                        this.disableField(fieldName)
                    }
                }
                this.openRequests++
                axios.post('/manuscripts/filtervalues', this.cleanFilterValues(), {
                    cancelToken: new axios.CancelToken((c) => {this.cancel = c})
                })
                    .then( (response) => {
                        this.openRequests--
                        for (let fieldName of Object.keys(this.schema.fields)) {
                            if (this.schema.fields[fieldName].type == 'multiselectClear') {
                                this.enableField(fieldName, response.data[fieldName] === undefined ? [] : response.data[fieldName].sort(this.sortByName))
                            }
                        }
                    })
                    .catch( (error) => {
                        this.openRequests--
                        if (!axios.isCancel(error)) {
                            console.log(error)
                        }
                    })
            },
            filterChanged() {
                this.setFilters()
                VueTables.Event.$emit('vue-tables.filter::filters', this.cleanFilterValues())
            },
            disableField(fieldName) {
                this.schema.fields[fieldName].disabled = true
                this.schema.fields[fieldName].placeholder = 'Loading'
                this.schema.fields[fieldName].selectOptions.loading = true
                this.schema.fields[fieldName].values = []
            },
            enableField(fieldName, values) {
                this.schema.fields[fieldName].selectOptions.loading = false
                this.schema.fields[fieldName].placeholder = (['origin'].indexOf(fieldName) < 0 ? 'Select a ' : 'Select an ') + fieldName
                // Handle dependencies
                if (this.schema.fields[fieldName].dependency !== undefined) {
                    let dependency = this.schema.fields[fieldName].dependency
                    if (this.model[dependency] === undefined || this.model[dependency] === null) {
                        this.schema.fields[fieldName].placeholder = 'Please select a ' + dependency + ' first'
                        return
                    }
                }
                // No results
                if (values.length === 0) {
                    if (this.model[fieldName] !== undefined && this.model[fieldName] !== null) {
                        this.schema.fields[fieldName].disabled = false
                        return
                    }
                    return
                }
                // Only one result
                if (values.length === 1) {
                    if (this.model[fieldName] === undefined || this.model[fieldName] === null || this.model[fieldName].derived) {
                        this.model[fieldName] = values[0]
                        this.model[fieldName].derived = true
                        return
                    }
                }
                // Default
                this.schema.fields[fieldName].disabled = false
                this.schema.fields[fieldName].values = values
            },
            sortByName(a, b) {
                if (a.name < b.name)
                    return -1
                if (a.name > b.name)
                    return 1
                return 0
            }
        }
    }
</script>
