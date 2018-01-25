<template>
    <div>
        <article class="col-sm-9">
            <h2>Search Manuscripts</h2>
            <div role="alert" class="alert alert-dismissible alert-danger" v-if="this.error">
                <button aria-label="Close" data-dismiss="alert" class="close" type="button"><span aria-hidden="true">×</span></button>
                <span class="sr-only">Error</span>
                {{ this.error }}
            </div>
            <vue-form-generator :schema="diktyonSchema" :model="model" :options="formOptions"></vue-form-generator>
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
            'getManuscriptUrl'
        ],
        data() {
            return {
                model: {
                    diktyon: null
                },
                diktyonSchema: {
                    fields: {
                        diktyon: {
                            type: 'input',
                            inputType: 'number',
                            label: 'Pinakes number',
                            model: 'diktyon',
                            validator: VueFormGenerator.validators.number

                        }
                    }
                },
                formOptions: {
                    validateAfterLoad: true,
                    validateAfterChanged: true,
                    validationErrorClass: "has-error",
                    validationSuccessClass: "success"
                },
                openRequests: 0,
                error: ''
            }
        },
        mounted () {
            this.$nextTick( () => {
                if (this.getManuscriptUrl !== undefined && this.getManuscriptUrl !== null) {
                    this.openRequests++
                    axios.get(this.getManuscriptUrl)
                        .then( (response) => {
                            this.model.diktyon = response.data.diktyon
                            console.log(this.model)
                            this.openRequests--
                        })
                        .catch( (error) => {
                            console.log(error)
                            this.error = 'Something whent wrong while loading the manuscript data.'
                            this.openRequests--
                        })
                }
            })
        },
        methods: {
            disableField(fieldName) {
                this.schema.fields[fieldName].disabled = true
                this.schema.fields[fieldName].placeholder = 'Loading'
                this.schema.fields[fieldName].selectOptions.loading = true
                this.schema.fields[fieldName].values = []
            },
            enableField(fieldName, values) {
                let label = this.schema.fields[fieldName].label.toLowerCase()
                this.schema.fields[fieldName].selectOptions.loading = false
                this.schema.fields[fieldName].placeholder = (['origin'].indexOf(label) < 0 ? 'Select a ' : 'Select an ') + label
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
                // Default
                this.schema.fields[fieldName].disabled = false
                this.schema.fields[fieldName].values = values
            }
        }
    }
</script>
