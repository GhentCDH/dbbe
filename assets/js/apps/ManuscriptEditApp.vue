<template>
    <div>
        <article class="col-sm-9">
            <h2>Edit Manuscript</h2>
            <div role="alert" class="alert alert-dismissible alert-danger" v-if="this.error">
                <button aria-label="Close" data-dismiss="alert" class="close" type="button"><span aria-hidden="true">×</span></button>
                <span class="sr-only">Error</span>
                {{ this.error }}
            </div>
            <vue-form-generator :schema="citySchema" :model="model" :options="formOptions"></vue-form-generator>
            <template v-if="this.model.diktyon !== undefined && this.model.diktyon !== null && !isNaN(this.model.diktyon)">
                <a :href="'http://pinakes.irht.cnrs.fr/notices/cote/id/' + this.model.diktyon">http://pinakes.irht.cnrs.fr/notices/cote/id/{{ this.model.diktyon }}</a>
            </template>
            <div class="loading-overlay" v-if="this.openRequests">
                <div class="spinner">
                </div>
            </div>
        </article>
    </div>
</template>

<script>
    window.axios = require('axios')

    import Vue from 'vue'
    import VueFormGenerator from 'vue-form-generator'
    import VueMultiselect from 'vue-multiselect'

    import fieldMultiselectClear from '../components/formfields/fieldMultiselectClear'

    Vue.use(VueFormGenerator)

    Vue.component('multiselect', VueMultiselect)
    Vue.component('fieldMultiselectClear', fieldMultiselectClear)

    var YEAR_MIN = 1
    var YEAR_MAX = (new Date()).getFullYear()

    export default {
        props: [
            'getManuscriptUrl',
            'getCitiesUrl'
        ],
        data() {
            return {
                model: {
                    city: null
                },
                citySchema: {
                    fields: {
                        city: this.createMultiSelect('City', {required: true}, this.addCity)
                    }
                },
                formOptions: {
                    validateAfterLoad: true,
                    validateAfterChanged: true,
                    validationErrorClass: "has-error",
                    validationSuccessClass: "success"
                },
                openRequests: 0,
                error: '',
                originalModel: {}
            }
        },
        mounted () {
            this.$nextTick( () => {
                this.openRequests++
                axios.get(this.getManuscriptUrl)
                    .then( (response) => {
                        this.model.city = response.data.city
                        this.originalModel = Object.assign({}, this.model)
                        this.openRequests--
                        this.loadCities()
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.error = 'Something whent wrong while loading the manuscript data.'
                        this.openRequests--
                    })
            })
        },
        methods: {
            createMultiSelect(label, extra, addTag) {
                let result = {
                    type: 'vueMultiSelect',
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
            loadCities() {
                this.openRequests++
                axios.get(this.getCitiesUrl)
                    .then( (response) => {
                        this.enableField(this.citySchema.fields.city, response.data.sort(this.sortByName))
                        this.openRequests--
                    })
                    .catch( (error) => {
                        console.log(error)
                        this.error = 'Something whent wrong while loading the manuscript data.'
                        this.openRequests--
                    })
            },
            disableField(field) {
                this.schema.fields[fieldName].disabled = true
                this.schema.fields[fieldName].placeholder = 'Loading'
                this.schema.fields[fieldName].selectOptions.loading = true
                this.schema.fields[fieldName].values = []
            },
            enableField(field, values) {
                let label = field.label.toLowerCase()
                field.selectOptions.loading = false
                field.placeholder = (['origin'].indexOf(label) < 0 ? 'Select a ' : 'Select an ') + label
                // Handle dependencies
                if (field.dependency !== undefined) {
                    let dependency = field.dependency
                    if (this.model[dependency] === undefined || this.model[dependency] === null) {
                        field.placeholder = 'Please select a ' + dependency + ' first'
                        return
                    }
                }
                // No results
                if (values.length === 0) {
                    return
                }
                // Default
                field.disabled = false
                field.values = values
                // Set value
                if (this.model[label] !== undefined) {
                    field.value = this.model[label]
                }
            },
            sortByName(a, b) {
                if (a.name < b.name) {
                    return -1
                }
                if (a.name > b.name) {
                    return 1
                }
                return 0
            }
        }
    }
</script>
