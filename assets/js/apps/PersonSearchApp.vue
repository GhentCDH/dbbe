<template>
    <div>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)" />
        </div>
        <aside class="col-sm-3">
            <div class="bg-tertiary padding-default">
                <div
                    v-if="JSON.stringify(model) !== JSON.stringify(originalModel)"
                    class="form-group">
                    <button
                        class="btn btn-block"
                        @click="resetAllFilters">
                        Reset all filters
                    </button>
                </div>
                <vue-form-generator
                    ref="form"
                    :schema="schema"
                    :model="model"
                    :options="formOptions"
                    @model-updated="modelUpdated"
                    @validated="onValidated" />
            </div>
        </aside>
        <article class="col-sm-9 search-page">
            <div
                v-if="countRecords"
                class="count-records">
                <h6>{{ countRecords }}</h6>
            </div>
            <v-server-table
                ref="resultTable"
                :url="urls['persons_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded">
                <template
                    slot="comment"
                    slot-scope="props">
                    <template v-if="props.row.public_comment">
                        <em v-if="isEditor">Public</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.public_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="item" />
                        </ol>
                    </template>
                    <template v-if="props.row.private_comment">
                        <em>Private</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.private_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="item" />
                        </ol>
                    </template>
                </template>
                <template
                    slot="name"
                    slot-scope="props">
                    <a
                        v-if="props.row.name.constructor !== Array"
                        :href="urls['person_get'].replace('person_id', props.row.id)">
                        {{ props.row.name }}
                    </a>
                    <template v-else>
                        <a
                            v-if="props.row.name.length === 1"
                            :href="urls['person_get'].replace('person_id', props.row.id)"
                            v-html="props.row.name[0]" />
                        <ul v-else>
                            <li
                                v-for="(item, index) in props.row.name"
                                :key="index"
                                v-html="item" />
                        </ul>
                    </template>
                </template>
                <template
                    v-if="(props.row.rgk != null && props.row.rgk.length > 0) || (props.row.vgh != null && props.row.vgh.length > 0) || (props.row.pbw != null && props.row.pbw !== '')"
                    slot="identification"
                    slot-scope="props">
                    {{ formatIdentification(props.row.rgk, props.row.vgh, props.row.pbw) }}
                </template>
                <template
                    v-if="props.row.born_date_floor_year || props.row.born_date_ceiling_year || props.row.death_date_floor_year || props.row.death_date_ceiling_year"
                    slot="date"
                    slot-scope="props">
                    {{ formatInterval(props.row.born_date_floor_year, props.row.born_date_ceiling_year, props.row.death_date_floor_year, props.row.death_date_ceiling_year) }}
                </template>
                <template
                    v-if="props.row.death_date_floor_year && props.row.death_date_ceiling_year"
                    slot="deathdate"
                    slot-scope="props">
                    <template v-if="props.row.death_date_floor_year === props.row.death_date_ceiling_year">
                        {{ props.row.death_date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.death_date_floor_year }} - {{ props.row.death_date_ceiling_year }}
                    </template>
                </template>
                <template
                    slot="actions"
                    slot-scope="props">
                    <a
                        :href="urls['person_edit'].replace('person_id', props.row.id)"
                        class="action"
                        title="Edit">
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        href="#"
                        class="action"
                        title="Merge"
                        @click.prevent="merge(props.row)">
                        <i class="fa fa-compress" />
                    </a>
                    <a
                        href="#"
                        class="action"
                        title="Delete"
                        @click.prevent="del(props.row)">
                        <i class="fa fa-trash-o" />
                    </a>
                </template>
            </v-server-table>
        </article>
        <mergeModal
            :show="mergeModal"
            :schema="mergePersonSchema"
            :merge-model="mergeModel"
            :original-merge-model="originalMergeModel"
            :alerts="mergeAlerts"
            @cancel="cancelMerge()"
            @reset="resetMerge()"
            @confirm="submitMerge()"
            @dismiss-alert="mergeAlerts.splice($event, 1)">
            <table
                v-if="mergeModel.primary && mergeModel.secondary"
                slot="preview"
                class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>First Name</td>
                        <td>{{ mergeModel.primary.firstName || mergeModel.secondary.firstName }}</td>
                    </tr>
                    <tr>
                        <td>Last Name</td>
                        <td>{{ mergeModel.primary.lastName || mergeModel.secondary.lastName }}</td>
                    </tr>
                    <tr>
                        <td>Extra</td>
                        <td>{{ mergeModel.primary.extra || mergeModel.secondary.extra }}</td>
                    </tr>
                    <tr>
                        <td>Unprocessed</td>
                        <td>{{ (mergeModel.primary.firstName || mergeModel.secondary.firstName || mergeModel.primary.lastName || mergeModel.secondary.lastName || mergeModel.primary.extra || mergeModel.secondary.extra) ? '' : mergeModel.primary.unprocessed || mergeModel.secondary.unprocessed }}</td>
                    </tr>
                    <tr>
                        <td>Historical</td>
                        <td>{{ mergeModel.primary.historical ? 'Yes' : 'No' }}</td>
                    </tr>
                    <tr>
                        <td>Born Date</td>
                        <td>{{ formatDate(mergeModel.primary.bornDate) || formatDate(mergeModel.secondary.bornDate) }}</td>
                    </tr>
                    <tr>
                        <td>Death Date</td>
                        <td>{{ formatDate(mergeModel.primary.deathDate) || formatDate(mergeModel.secondary.deathDate) }}</td>
                    </tr>
                    <tr>
                        <td>RGK</td>
                        <td>{{ mergeModel.primary.rgk || mergeModel.secondary.rgk }}</td>
                    </tr>
                    <tr>
                        <td>VGH</td>
                        <td>{{ mergeModel.primary.vgh || mergeModel.secondary.vgh }}</td>
                    </tr>
                    <tr>
                        <td>PBW</td>
                        <td>{{ mergeModel.primary.pbw || mergeModel.secondary.pbw }}</td>
                    </tr>
                    <tr>
                        <td>Types</td>
                        <td>{{ formatOccupations(mergeModel.primary.types) || formatOccupations(mergeModel.secondary.types) }}</td>
                    </tr>
                    <tr>
                        <td>Functions</td>
                        <td>{{ formatOccupations(mergeModel.primary.functions) || formatOccupations(mergeModel.secondary.functions) }}</td>
                    </tr>
                    <tr>
                        <td>Public comment</td>
                        <td>{{ mergeModel.primary.publicComment || mergeModel.secondary.publicComment }}</td>
                    </tr>
                    <tr>
                        <td>Private comment</td>
                        <td>{{ mergeModel.primary.privateComment || mergeModel.secondary.privateComment }}</td>
                    </tr>
                </tbody>
            </table>
        </mergeModal>
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            @cancel="deleteModal=false"
            @confirm="submitDelete()" />
        <div
            v-if="openRequests"
            class="loading-overlay">
            <div class="spinner" />
        </div>
    </div>
</template>
<script>
import VueFormGenerator from 'vue-form-generator'

import AbstractField from '../Components/FormFields/AbstractField'
import AbstractSearch from '../Components/Search/AbstractSearch'

// used for deleteDependencies, mergeModal
import AbstractListEdit from '../Components/Edit/AbstractListEdit'

export default {
    mixins: [
        AbstractField,
        AbstractSearch,
        AbstractListEdit, // merge functionality
    ],
    props: {
        initPersons: {
            type: String,
            default: '',
        },
    },
    data() {
        let data = {
            persons: JSON.parse(this.initPersons),
            schema: {
                fields: {
                    name: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Name',
                        model: 'name',
                    },
                    historical: this.createMultiSelect(
                        'Historical',
                        {},
                        {
                            customLabel: ({id, name}) => {
                                return name === 'true' ? 'Historical only' : 'Non-historical only'
                            },
                        }
                    ),
                    year_from: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year from',
                        model: 'year_from',
                        min: AbstractSearch.YEAR_MIN,
                        max: AbstractSearch.YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    year_to: {
                        type: 'input',
                        inputType: 'number',
                        label: 'Year to',
                        model: 'year_to',
                        min: AbstractSearch.YEAR_MIN,
                        max: AbstractSearch.YEAR_MAX,
                        validator: VueFormGenerator.validators.number,
                    },
                    type: this.createMultiSelect('Type'),
                    function: this.createMultiSelect('Function'),
                    rgk: this.createMultiSelect('RGK', {model: 'rgk'}),
                    vgh: this.createMultiSelect('VGH', {model: 'vgh'}),
                    pbw: this.createMultiSelect('PBW', {model: 'pbw'}),
                    comment: {
                        type: 'input',
                        inputType: 'text',
                        label: 'Comment',
                        model: 'comment',
                        validator: VueFormGenerator.validators.string,
                    },
                }
            },
            tableOptions: {
                headings: {
                    comment: 'Comment (matching lines only)',
                },
                'filterable': false,
                'orderBy': {
                    'column': 'name'
                },
                'perPage': 25,
                'perPageValues': [25, 50, 100],
                'sortable': ['name', 'date'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback: function(row) {
                    return (row.public == null || row.public) ? '' : 'warning'
                },
            },
            mergePersonSchema: {
                fields: {
                    primary: this.createMultiSelect('Primary', {required: true, validator: VueFormGenerator.validators.required}),
                    secondary: this.createMultiSelect('Secondary', {required: true, validator: VueFormGenerator.validators.required}),
                },
            },
            mergeModel: {
                type: 'persons',
                primary: null,
                secondary: null,
            },
            submitModel: {
                type: 'person',
                person: {},
            },
            defaultOrdering: 'name',
        }

        // Add view internal only fields
        if (this.isViewInternal) {
            data.schema.fields['public'] = this.createMultiSelect(
                'Public',
                {
                    styleClasses: 'has-warning',
                },
                {
                    customLabel: ({id, name}) => {
                        return name === 'true' ? 'Public only' : 'Internal only'
                    },
                }
            )
        }

        return data
    },
    computed: {
        depUrls: function () {
            return {
                'Manuscripts': {
                    depUrl: this.urls['manuscript_deps_by_person'].replace('person_id', this.submitModel.person.id),
                    url: this.urls['manuscript_get'],
                    urlIdentifier: 'manuscript_id',
                },
                'Occurrences': {
                    depUrl: this.urls['occurrence_deps_by_person'].replace('person_id', this.submitModel.person.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
                // TODO: books, bookchapters, article
            }
        },
        tableColumns() {
            let columns = ['name', 'identification', 'date']
            if (this.commentSearch) {
                columns.unshift('comment')
            }
            if (this.isEditor) {
                columns.push('actions')
            }
            return columns
        },
    },
    methods: {
        merge(row) {
            this.mergeModel.primary = JSON.parse(JSON.stringify(this.persons.filter(person => person.id === row.id)[0]))
            this.mergeModel.secondary = null
            this.mergePersonSchema.fields.primary.values = this.persons
            this.mergePersonSchema.fields.secondary.values = this.persons
            this.enableField(this.mergePersonSchema.fields.primary)
            this.enableField(this.mergePersonSchema.fields.secondary)
            this.originalMergeModel = JSON.parse(JSON.stringify(this.mergeModel))
            this.mergeModal = true
        },
        del(row) {
            this.submitModel.person = {
                id: row.id,
                name: row.original_name == null ? row.name : row.original_name,
            }
            AbstractListEdit.methods.deleteDependencies.call(this)
        },
        submitMerge() {
            this.mergeModal = false
            this.openRequests++
            axios.put(this.urls['person_merge'].replace('primary_id', this.mergeModel.primary.id).replace('secondary_id', this.mergeModel.secondary.id))
                .then( (response) => {
                    this.$refs.resultTable.refresh()
                    this.mergeAlerts = []
                    this.alerts.push({type: 'success', message: 'Merge successful.'})
                    this.openRequests--
                })
                .catch( (error) => {
                    this.openRequests--
                    this.mergeModal = true
                    this.mergeAlerts.push({type: 'error', message: 'Something went wrong while merging the persons.', login: this.isLoginError(error)})
                    console.log(error)
                })
        },
        submitDelete() {
            this.openRequests++
            this.deleteModal = false
            axios.delete(this.urls['person_delete'].replace('person_id', this.submitModel.person.id))
                .then((response) => {
                    this.$refs.resultTable.refresh()
                    this.openRequests--
                    this.alerts.push({type: 'success', message: 'Person deleted successfully.'})
                })
                .catch((error) => {
                    this.openRequests--
                    this.alerts.push({type: 'error', message: 'Something went wrong while deleting the person.'})
                    console.log(error)
                })
        },
        formatDate(date) {
            if (date == null || date.floor == null || date.ceiling == null) {
                return null
            }
            return date.floor + ' - ' + date.ceiling
        },
        formatInterval(born_floor, born_ceiling, death_floor, death_ceiling) {
            let born = born_floor === born_ceiling ? born_floor : born_floor + '-' + born_ceiling
            let death = death_floor === death_ceiling ? death_floor : death_floor + '-' + death_ceiling
            return born === death ? born : '(' + born + ') - (' + death + ')'
        },
        formatOccupations(occupations) {
            if (occupations == null || occupations.length === 0) {
                return null
            }
            return occupations.map(occupation => occupation.name).join(', ')
        },
        formatIdentification(rgk, vgh, pbw) {
            let result = []
            if (rgk != null && rgk.length > 0) {
                result.push('RGK: ' + rgk.join(', '))
            }
            if (vgh != null && vgh.length > 0) {
                result.push('VGH: ' + vgh.join(', '))
            }
            if (pbw != null && pbw !== '') {
                result.push('PBW: ' + pbw)
            }
            return result.join(' - ')
        },
    }
}
</script>
