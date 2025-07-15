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
                <div class="form-group">
                    <a
                        :href="urls['help']"
                        class="action"
                        target="_blank"
                    >
                        <i class="fa fa-info-circle" /> More information about the text search options.</a>
                </div>
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
                :url="urls['types_search_api']"
                :columns="tableColumns"
                :options="tableOptions"
                @data="onData"
                @loaded="onLoaded"
            >
                <span
                    slot="text"
                    slot-scope="props"
                >
                    <template v-if="props.row.title">
                        <!-- T for title: T is the 20th letter in the alphabet -->
                        <ol
                            type="A"
                            class="greek"
                        >
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.title"
                                :key="index"
                                value="20"
                                v-html="item"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                    <template v-if="props.row.text">
                        <ol class="greek">
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.text"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="item"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                </span>
                <template
                    slot="comment"
                    slot-scope="props"
                >
                    <template v-if="props.row.public_comment">
                        <em v-if="isViewInternal">Public</em>
                        <ol>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.public_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                    <template v-if="props.row.private_comment">
                        <em>Private</em>
                        <ol>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.private_comment"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                </template>
                <template
                    slot="lemma"
                    slot-scope="props"
                >
                    <template v-if="props.row.lemma_text">
                        <ol>
                            <!-- eslint-disable vue/no-v-html -->
                            <li
                                v-for="(item, index) in props.row.lemma_text"
                                :key="index"
                                :value="Number(index) + 1"
                                v-html="greekFont(item)"
                            />
                            <!-- eslint-enable -->
                        </ol>
                    </template>
                </template>
                <!-- eslint-disable vue/no-v-html -->
                <a
                    slot="id"
                    slot-scope="props"
                    :href="urls['type_get'].replace('type_id', props.row.id)"
                >
                    {{ props.row.id }}
                </a>
                <a
                    slot="incipit"
                    slot-scope="props"
                    :href="urls['type_get'].replace('type_id', props.row.id)"
                    class="greek"
                    v-html="props.row.incipit"
                />
                <!-- eslint-enable -->
                <template
                    slot="numberOfOccurrences"
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
                        :href="urls['type_edit'].replace('type_id', props.row.id)"
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
import qs from 'qs';
import VueFormGenerator from 'vue-form-generator';
import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import AbstractSearch from '../mixins/AbstractSearch'
import ActiveFilters from '../Components/Search/ActiveFilters.vue';
import {formatDate, greekFont} from "@/helpers/formatUtil";
import {useSearchSession} from "../composables/useSearchSession";
import {isLoginError} from "@/helpers/errorUtil";

export default {
    components: { ActiveFilters },
    mixins: [
        AbstractSearch
    ],
    data() {
        const data = {
            model: {
                text_mode: ['greek'],
                comment_mode: ['latin'],
                text_fields: 'text',
                text_combination: 'all',
                person: [],
                role: [],
                metre: [],
                metre_op: 'or',
                genre: [],
                genre_op: 'or',
                subject: [],
                subject_op: 'or',
                tag: [],
                tag_op: 'or',
                translation_language: [],
                translation_language_op: 'or',
                acknowledgement: [],
                acknowledgement_op: 'or',
            },
            schema: {
                fields: {},
            },
            tableOptions: {
                headings: {
                    text: 'Title (T.) / text (matching verses only)',
                    comment: 'Comment (matching lines only)',
                    lemma: 'Lemma (matching lines in original text only)',
                },
                columnsClasses: {
                    id: 'no-wrap',
                },
                filterable: false,
                orderBy: {
                    column: 'incipit',
                },
                perPage: 25,
                perPageValues: [25, 50, 100],
                sortable: ['id', 'incipit', 'number_of_occurrences', 'created', 'modified'],
                customFilters: ['filters'],
                requestFunction: AbstractSearch.requestFunction,
                rowClassCallback(row) {
                    return (row.public == null || row.public) ? '' : 'warning';
                },
            },
            submitModel: {
                submitType: 'type',
                type: {},
            },
            defaultOrdering: 'incipit',
            config: {
              groupIsOpen: [],
            },
            defaultConfig: {
              groupIsOpen: [],
            },
        };

        // Add fields
        data.schema.fields.text_mode = createLanguageToggle('text');
        data.schema.fields.text = {
            type: 'input',
            inputType: 'text',
            styleClasses: 'greek',
            labelClasses: 'control-label',
            label: 'Text',
            model: 'text',
        };
        if (this.isViewInternal) {
            data.model.text_stem = 'original';
            data.schema.fields.text_stem = {
                type: 'radio',
                styleClasses: 'has-warning',
                label: 'Stemmer options:',
                labelClasses: 'control-label',
                model: 'text_stem',
                values: [
                    { value: 'original', name: 'Original text' },
                    { value: 'stemmer', name: 'Stemmed text' },
                ],
            };
        }
        data.schema.fields.text_combination = {
            type: 'checkboxes',
            styleClasses: 'field-checkboxes-labels-only field-checkboxes-lg',
            label: 'Word combination options:',
            model: 'text_combination',
            parentModel: 'text',
            values: [
                { value: 'all', name: 'all', toggleGroup: 'all_any_phrase' },
                { value: 'any', name: 'any', toggleGroup: 'all_any_phrase' },
                { value: 'phrase', name: 'consecutive words', toggleGroup: 'all_any_phrase' },
            ],
        };
        data.schema.fields.text_fields = {
            type: 'checkboxes',
            styleClasses: 'field-checkboxes-labels-only field-checkboxes-lg',
            label: 'Which fields should be searched:',
            model: 'text_fields',
            parentModel: 'text',
            values: [
                { value: 'text', name: 'Text', toggleGroup: 'text_title_all' },
                { value: 'title', name: 'Title', toggleGroup: 'text_title_all' },
                { value: 'all', name: 'Text and title', toggleGroup: 'text_title_all' },
            ],
        };
        data.model.lemma_mode = ['greek'];
        data.schema.fields.lemma_mode = createLanguageToggle('lemma');
        // disable latin
        data.schema.fields.lemma_mode.values[2].disabled = true;
        data.schema.fields.lemma = {
            type: 'input',
            inputType: 'text',
            styleClasses: 'greek',
            labelClasses: 'control-label',
            label: 'Lemma',
            model: 'lemma',
        };
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
        [data.schema.fields.metre_op, data.schema.fields.metre] = createMultiMultiSelect('Metre');
        [data.schema.fields.genre_op, data.schema.fields.genre] = createMultiMultiSelect('Genre');
        [data.schema.fields.subject_op, data.schema.fields.subject] = createMultiMultiSelect('Subject');
        [data.schema.fields.tag_op, data.schema.fields.tag] = createMultiMultiSelect('Tag');
        data.schema.fields.translated = createMultiSelect(
            'Translation(s) available?',
            {
                model: 'translated',
            },
            {
                customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
            },
        );
        [
            data.schema.fields.translation_language_op,
            data.schema.fields.translation_language,
        ] = createMultiMultiSelect(
            'Translation language',
            {
                dependency: 'translated',
                model: 'translation_language',
            },
        );
        data.schema.fields.comment_mode = createLanguageToggle('comment');
        data.schema.fields.comment = {
            type: 'input',
            inputType: 'text',
            label: 'Comment',
            labelClasses: 'control-label',
            model: 'comment',
            validator: VueFormGenerator.validators.string,
        };

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
            ));
        }

        data.schema.groups = [
            {
                styleClasses: 'collapsible collapsed',
                legend: 'External identifiers',
                fields: idList,
            },
        ];
        data.schema.fields.id = createMultiSelect('DBBE ID', { model: 'id' });
        data.schema.fields.prev_id = createMultiSelect('Former DBBE ID', { model: 'prev_id' });

        data.schema.fields.dbbe = createMultiSelect(
            'Text source DBBE?',
            {
                model: 'dbbe',
            },
            {
                customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
            },
        );
        [data.schema.fields.acknowledgement_op, data.schema.fields.acknowledgement] = createMultiMultiSelect(
            'Acknowledgements',
            {
                model: 'acknowledgement',
            },
        );
        if (this.isViewInternal) {
            data.schema.fields.text_status = createMultiSelect(
                'Text Status',
                {
                    model: 'text_status',
                    styleClasses: 'has-warning',
                },
            );
            data.schema.fields.critical_status = createMultiSelect(
                'Editorial Status',
                {
                    model: 'critical_status',
                    styleClasses: 'has-warning',
                },
            );
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
      this.onData = this.session.onData;
      this.session.init();
      this.session = useSearchSession(this, 'TypeSearchConfig');
    },
    mounted(){
      this.session.setupCollapsibleLegends();
      this.$on('config-changed', this.session.handleConfigChange(this.schema));
    },
    computed: {
        depUrls() {
            return {
                Occurrences: {
                    depUrl: this.urls.occurrence_deps_by_type.replace('type_id', this.submitModel.type.id),
                    url: this.urls.occurrence_get,
                    urlIdentifier: 'occurrence_id',
                },
            };
        },
        tableColumns() {
            const columns = ['id', 'incipit', 'number_of_occurrences'];
            if (this.textSearch) {
                columns.unshift('text');
            }
            if (this.commentSearch) {
                columns.unshift('comment');
            }
            if (this.lemmaSearch) {
                columns.unshift('lemma');
            }
            if (this.isViewInternal) {
                columns.push('created');
                columns.push('modified');
                columns.push('actions');
                columns.push('c');
            }
            return columns;
        },
    },
    watch: {
        'model.lemma_mode': function (value, oldValue) {
            this.changeTextMode(value, oldValue, 'lemma');
        },
    },
    methods: {
      greekFont,
      formatDate,
      del(row) {
        this.submitModel.type = {
          id: row.id,
          name: row.incipit,
        };        this.openRequests += 1;
        const depUrlsEntries = Object.entries(this.depUrls);

        axios
            .all(depUrlsEntries.map(([_, depUrlCat]) => axios.get(depUrlCat.depUrl)))
            .then(results => {
              this.delDependencies = {};

              results.forEach((response, index) => {
                const data = response.data;
                if (data.length > 0) {
                  const [category, depUrlCat] = depUrlsEntries[index];
                  this.delDependencies[category] = {
                    list: data,
                    ...(depUrlCat.url && { url: depUrlCat.url }),
                    ...(depUrlCat.urlIdentifier && { urlIdentifier: depUrlCat.urlIdentifier }),
                  };
                }
              });

              this.deleteModal = true;
              this.openRequests -= 1;
            })
            .catch(error => {
              this.openRequests -= 1;
              this.alerts.push({
                type: 'error',
                message: 'Something went wrong while checking for dependencies.',
                login: isLoginError(error),
              });
              console.error(error);
            });
      },

      submitDelete() {
            this.openRequests += 1;
            this.deleteModal = false;
            axios.delete(this.urls.type_delete.replace('type_id', this.submitModel.type.id))
                .then((_response) => {
                    // Don't create a new history item
                    this.noHistory = true;
                    this.$refs.resultTable.refresh();
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'success', message: 'Type deleted successfully.' });
                })
                .catch((error) => {
                    this.openRequests -= 1;
                    this.alerts.push({ type: 'error', message: 'Something went wrong while deleting the type.' });
                    console.error(error);
                });
        },
        async downloadCSV() {
          try {
            const params = this.getSearchParams();
            params.limit = 10000;
            params.page = 1;

            const queryString = qs.stringify(params, { encode: true, arrayFormat: 'brackets' });
            const url = `${this.urls['types_export_csv']}?${queryString}`;

            const response = await fetch(url);
            const blob = await response.blob();

            this.downloadFile(blob, 'types.csv', 'text/csv');
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
