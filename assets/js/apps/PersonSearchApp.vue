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
                            v-html="props.row.name[0]">
                        </a>
                        <ul v-else>
                            <li
                                v-for="(item, index) in props.row.name"
                                :key="index"
                                v-html="item" />
                        </ul>
                    </template>
                </template>
                <template
                    v-if="props.row.rgk || props.row.vgh || props.row.pbw"
                    slot="identification"
                    slot-scope="props">
                    {{ formatIdentification(props.row.rgk, props.row.vgh, props.row.pbw) }}
                </template>
                <template
                    v-if="props.row.born_date_floor_year || props.row.born_date_ceiling_year || props.row.death_date_floor_year || props.row.death_date_ceiling_year"
                    slot="date"
                    slot-scope="props">
                    {{ formatDate(props.row.born_date_floor_year, props.row.born_date_ceiling_year, props.row.death_date_floor_year, props.row.death_date_ceiling_year) }}
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
                        title="Delete"
                        @click.prevent="del(props.row)">
                        <i class="fa fa-trash-o" />
                    </a>
                </template>
            </v-server-table>
        </article>
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

import AbstractListEdit from '../Components/Edit/AbstractListEdit'

export default {
    mixins: [
        AbstractField,
        AbstractSearch,
    ],
    data() {
        let data = {
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
                    function: this.createMultiSelect('Function'),
                    type: this.createMultiSelect('Type'),
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
                    urlIdentifier: 'occurrence_id',
                },
                'Occurrences': {
                    depUrl: this.urls['occurrence_deps_by_person'].replace('person_id', this.submitModel.person.id),
                    url: this.urls['occurrence_get'],
                    urlIdentifier: 'occurrence_id',
                },
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
        del(row) {
            this.submitModel.person = row
            AbstractListEdit.methods.deleteDependencies.call(this)
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
                    this.alerts.push({type: 'error', message: 'Something whent wrong while deleting the person.'})
                    console.log(error)
                })
        },
        formatDate(born_floor, born_ceiling, death_floor, death_ceiling) {
            let born = born_floor === born_ceiling ? born_floor : born_floor + '-' + born_ceiling
            let death = death_floor === death_ceiling ? death_floor : death_floor + '-' + death_ceiling
            return born === death ? born : '(' + born + ') - (' + death + ')'
        },
        formatIdentification(rgk, vgh, pbw) {
            let result = []
            if (rgk) {
                result.push('RGK: ' + rgk)
            }
            if (vgh) {
                result.push('VGH: ' + vgh)
            }
            if (pbw) {
                result.push('PBW: ' + pbw)
            }
            return result.join(', ')
        },
    }
}
</script>
