<template>
    <div>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
        </div>
        <aside class="col-sm-3">
            <div class="bg-tertiary padding-default">
                <vue-form-generator
                    ref="form"
                    :schema="schema"
                    :model="model"
                    :options="formOptions"
                    @model-updated="modelUpdated"
                    @validated="onValidated"
                />
            </div>
        </aside>
        <article class="col-sm-9 search-page">
            <active-filters
                :filters="notEmptyFields"
                class="active-filters"
                @resetFilters="resetAllFilters"
                @deletedActiveFilter="deleteActiveFilter"
            />
            <div
                v-if="countRecords"
                class="count-records"
            >
                <h6>{{ countRecords }}</h6>
            </div>
            <div
                v-if="isViewInternal"
                class="collection-select-all top"
            >
                <a
                    href="#"
                    @click.prevent="clearCollection()"
                >
                    clear selection
                </a>
                |
                <a
                    href="#"
                    @click.prevent="collectionToggleAll()"
                >
                    (un)select all on this page
                </a>
            </div>
            <v-server-table
                ref="resultTable"
                :url="urls['manuscripts_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded"
            >
                <template
                    slot="comment"
                    slot-scope="props"
                >
                    <template v-if="props.row.public_comment">
                        <em v-if="isEditor">Public</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.public_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                        </ol>
                    </template>
                    <template v-if="props.row.private_comment">
                        <em>Private</em>
                        <ol>
                            <li
                                v-for="(item, index) in props.row.private_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                        </ol>
                    </template>
                </template>
                <a
                    slot="name"
                    slot-scope="props"
                    :href="urls['manuscript_get'].replace('manuscript_id', props.row.id)"
                >
                    {{ props.row.name }}
                </a>
                <template
                    v-if="props.row.date_floor_year && props.row.date_ceiling_year"
                    slot="date"
                    slot-scope="props"
                >
                    <template v-if="props.row.date_floor_year === props.row.date_ceiling_year">
                        {{ props.row.date_floor_year }}
                    </template>
                    <template v-else>
                        {{ props.row.date_floor_year }} - {{ props.row.date_ceiling_year }}
                    </template>
                </template>
                <template
                    v-if="props.row.content"
                    slot="content"
                    slot-scope="props"
                >
                    <!-- set displayContent using a v-for -->
                    <!-- eslint-disable-next-line max-len -->
                    <template v-for="(displayContent, index) in [props.row.content.filter((content) => content['display'])]">
                        <ul
                            v-if="displayContent.length > 1"
                            :key="index"
                        >
                            <li
                                v-for="(content, contentIndex) in displayContent"
                                :key="contentIndex"
                            >
                                {{ content.name }}
                            </li>
                        </ul>
                        <template v-else>
                            {{ displayContent[0].name }}
                        </template>
                    </template>
                </template>
                <template
                    slot="occ"
                    slot-scope="props"
                >
                    {{ props.row.number_of_occurrences }}
                </template>
                <template
                    slot="created"
                    slot-scope="props"
                >
                    {{ formatDate(props.row.created) }}
                </template>
                <template
                    slot="modified"
                    slot-scope="props"
                >
                    {{ formatDate(props.row.modified) }}
                </template>
                <template
                    slot="actions"
                    slot-scope="props"
                >
                    <a
                        :href="urls['manuscript_edit'].replace('manuscript_id', props.row.id)"
                        class="action"
                        title="Edit"
                    >
                        <i class="fa fa-pencil-square-o" />
                    </a>
                    <a
                        href="#"
                        class="action"
                        title="Delete"
                        @click.prevent="del(props.row)"
                    >
                        <i class="fa fa-trash-o" />
                    </a>
                </template>
                <template
                    slot="c"
                    slot-scope="props"
                >
                    <span class="checkbox checkbox-primary">
                        <input
                            :id="props.row.id"
                            v-model="collectionArray"
                            :name="props.row.id"
                            :value="props.row.id"
                            type="checkbox"
                        >
                        <label :for="props.row.id" />
                    </span>
                </template>
            </v-server-table>
            <div
                v-if="isViewInternal"
                class="collection-select-all bottom"
            >
                <a
                    href="#"
                    @click.prevent="clearCollection()"
                >
                    clear selection
                </a>
                |
                <a
                    href="#"
                    @click.prevent="collectionToggleAll()"
                >
                    (un)select all on this page
                </a>
            </div>
<!--          <div style="position: relative; height: 100px;">-->
<!--            <button @click="downloadCSV"-->
<!--                    class="btn btn-primary"-->
<!--                    style="position: absolute; top: 50%; right: 1rem; transform: translateY(-50%);">-->
<!--              Download results CSV-->
<!--            </button>-->
<!--          </div>-->
            <collectionManager
                v-if="isViewInternal"
                :collection-array="collectionArray"
                :managements="managements"
                @addManagementsToSelection="addManagementsToSelection"
                @removeManagementsFromSelection="removeManagementsFromSelection"
                @addManagementsToResults="addManagementsToResults"
                @removeManagementsFromResults="removeManagementsFromResults"
            />
        </article>
        <div class="col-xs-12">
            <alerts
                :alerts="alerts"
                @dismiss="alerts.splice($event, 1)"
            />
        </div>
        <deleteModal
            :show="deleteModal"
            :del-dependencies="delDependencies"
            :submit-model="submitModel"
            @cancel="deleteModal=false"
            @confirm="submitDelete()"
        />
        <transition name="fade">
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
                <div class="spinner" />
            </div>
        </transition>
    </div>
</template>
<script>
import Vue from 'vue/dist/vue.js';
import qs from 'qs';

import VueFormGenerator from 'vue-form-generator';
import axios from 'axios';

import AbstractSearch from '../mixins/AbstractSearch';

// used for deleteDependencies
import AbstractListEdit from '../mixins/AbstractListEdit';

import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import ActiveFilters from '../Components/Search/ActiveFilters.vue';


import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import PersistentConfig from "@/mixins/PersistentConfig";
import {formatDate, greekFont} from "@/helpers/formatUtil";
import {useSearchSession} from "@/composables/useSearchSession";
Vue.component('FieldRadio', fieldRadio);

export default {
    components: { ActiveFilters },
    mixins: [
        AbstractSearch,
      PersistentConfig('ManuscriptSearchConfig'),
    ],
    data() {
        const data = {
            model: {
                date_search_type: 'exact',
                person: [],
                role: [],
                content: [],
                content_op: 'or',
                origin: [],
                origin_op: 'or',
                comment_mode: ['latin'],
                acknowledgement: [],
                acknowledgement_op: 'or',
            },
            schema: {
                fields: {},
            },
            tableOptions: {
                headings: {
                    comment: 'Comment (matching lines only)',
                },
                filterable: false,
                orderBy: {
                    column: 'name',
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['name', 'date', 'occ', 'created', 'modified'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback(row) {
                    return (row.public == null || row.public) ? '' : 'warning';
                },
            },
            submitModel: {
                submitType: 'manuscript',
                manuscript: {},
            },
            defaultOrdering: 'name',
        };

        // Add fields
        data.schema.fields.city = createMultiSelect('City');
        data.schema.fields.library = createMultiSelect('Library', { dependency: 'city' });
        data.schema.fields.collection = createMultiSelect('Collection', { dependency: 'library' });
        data.schema.fields.shelf = createMultiSelect('Shelf number', { model: 'shelf', dependency: 'collection' });
        data.schema.fields.year_from = {
            type: 'input',
            inputType: 'number',
            label: 'Year from',
            model: 'year_from',
            min: AbstractSearch.YEAR_MIN,
            max: AbstractSearch.YEAR_MAX,
            validator: VueFormGenerator.validators.number,
        };
        data.schema.fields.year_to = {
            type: 'input',
            inputType: 'number',
            label: 'Year to',
            model: 'year_to',
            min: AbstractSearch.YEAR_MIN,
            max: AbstractSearch.YEAR_MAX,
            validator: VueFormGenerator.validators.number,
        };
        data.schema.fields.date_search_type = {
            type: 'checkboxes',
            styleClasses: 'field-checkboxes-labels-only field-checkboxes-lg',
            label: 'The occurrence date interval must ... the search date interval:',
            model: 'date_search_type',
            values: [
                { value: 'exact', name: 'exact', toggleGroup: 'exact_included_overlap' },
                { value: 'included', name: 'include', toggleGroup: 'exact_included_overlap' },
                { value: 'overlap', name: 'overlap', toggleGroup: 'exact_included_overlap' },
            ],
        };
        [data.schema.fields.content_op, data.schema.fields.content] = createMultiMultiSelect('Content');
        data.schema.fields.person = createMultiSelect(
            'Person',
            {},
            {
                multiple: true,
                closeOnSelect: false,
            },
        );
        data.schema.fields.role = createMultiSelect(
            'Role',
            {
                dependency: 'person',
            },
            {
                multiple: true,
                closeOnSelect: false,
            },
        );
        [data.schema.fields.origin_op, data.schema.fields.origin] = createMultiMultiSelect('Origin');
        data.schema.fields.comment_mode = createLanguageToggle('comment');
        data.schema.fields.comment = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
            model: 'comment',
            validator: VueFormGenerator.validators.string,
        };
        [data.schema.fields.acknowledgement_op, data.schema.fields.acknowledgement] = createMultiMultiSelect(
            'Acknowledgements',
            {
                model: 'acknowledgement',
            },
        );

        // Add identifier fields
        const idList = [];
        for (const identifier of JSON.parse(this.initIdentifiers)) {
            idList.push(createMultiSelect(
                `${identifier.name} available?`,
                {
                    model: `${identifier.systemName}_available`,
                },
                {
                    customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
                },
            ));
            idList.push(createMultiSelect(
                identifier.name,
                {
                    dependency: `${identifier.systemName}_available`,
                    model: identifier.systemName,
                },
                {
                  optionsLimit: 7000
                }
            ));
        }

        data.schema.groups = [
            {
                styleClasses: 'collapsible collapsed',
                legend: 'External identifiers',
                fields: idList,
            },
        ];

        // Add view internal only fields
        if (this.isViewInternal) {
            data.schema.fields.public = createMultiSelect(
                'Public',
                {
                    styleClasses: 'has-warning',
                },
                {
                    customLabel: ({ _id, name }) => (name === 'true' ? 'Public only' : 'Internal only'),
                },
            );
            data.schema.fields.management = createMultiSelect(
                'Management collection',
                {
                    model: 'management',
                    styleClasses: 'has-warning',
                },
            );
            data.schema.fields.management_inverse = {
                type: 'checkbox',
                styleClasses: 'has-warning',
                label: 'Inverse management collection selection',
                labelClasses: 'control-label',
                model: 'management_inverse',
            };
        }

        return data;
    },
    created(){
      this.session = useSearchSession(this);
      this.onData = (data) => this.session.onData(data, this.onDataExtend);
      this.session.init();
    },
    mounted(){
      this.session.setupCollapsibleLegends();
      this.$on('config-changed', this.session.handleConfigChange(this.schema));
    },
    computed: {
        depUrls() {
            return {
                Occurrences: {
                    depUrl: this.urls.occurrence_deps_by_manuscript.replace(
                        'manuscript_id',
                        this.submitModel.manuscript.id,
                    ),
                    url: this.urls.occurrence_get,
                    urlIdentifier: 'occurrence_id',
                },
            };
        },
        tableColumns() {
            const columns = ['name', 'date', 'content'];
            if (this.commentSearch) {
                columns.unshift('comment');
            }
            if (this.isViewInternal) {
                columns.push('occ');
                columns.push('created');
                columns.push('modified');
                columns.push('actions');
                columns.push('c');
            }
            return columns;
        },
    },
    methods: {
      greekFont,
      formatDate,
        del(row) {
            this.submitModel.manuscript = row;
            AbstractListEdit.methods.deleteDependencies.call(this);
        },
        submitDelete() {
            this.openRequests += 1;
            this.deleteModal = false;
            axios.delete(this.urls.manuscript_delete.replace('manuscript_id', this.submitModel.manuscript.id))
                .then((_response) => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'success', message: 'Manuscript deleted successfully.' });
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'error', message: 'Something went wrong while deleting the manuscript.' });
                    console.error(error);
                });
        },
      async downloadCSV() {
        try {
          const params = this.getSearchParams();
          params.limit = 10000;
          params.page = 1;

          const queryString = qs.stringify(params, { encode: true, arrayFormat: 'brackets' });
          const url = `${this.urls['manuscripts_export_csv']}?${queryString}`;

          const response = await fetch(url);
          const blob = await response.blob();

          this.downloadFile(blob, 'manuscripts.csv', 'text/csv');
        } catch (error) {
          console.error(error);
          this.alerts.push({ type: 'error', message: 'Error downloading CSV.' });
        }
      },
      downloadFile(blob, fileName, mimeType) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', fileName);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
      }

    },
};
</script>
