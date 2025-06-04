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
            <i class="fa fa-info-circle" />
            More information about the text search options.
          </a>
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
          :url="urls['occurrences_search_api']"
          :columns="tableColumns"
          :options="tableOptions"
          @data="onData"
          @loaded="onLoaded"
      >
                <span
                    slot="text"
                    slot-scope="props"
                    class="greek"
                >
                    <template v-if="props.row.title">
                        <ol type="A">
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
                        <ol>
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
          <template v-if="props.row.palaeographical_info">
            <em>Palaeographical info</em>
            <ol>
              <!-- eslint-disable vue/no-v-html -->
              <li
                  v-for="(item, index) in props.row.palaeographical_info"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
              <!-- eslint-enable -->
            </ol>
          </template>
          <template v-if="props.row.contextual_info">
            <em>Contextual info</em>
            <ol>
              <!-- eslint-disable vue/no-v-html -->
              <li
                  v-for="(item, index) in props.row.contextual_info"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
              <!-- eslint-enable -->
            </ol>
          </template>
          <template v-if="props.row.public_comment">
            <em v-if="isViewInternal">Public comment</em>
            <em v-else>Comment</em>
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
            <em>Private comment</em>
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
        <a
            slot="id"
            slot-scope="props"
            :href="urls['occurrence_get'].replace('occurrence_id', props.row.id)"
        >
          {{ props.row.id }}
        </a>
        <a
            slot="incipit"
            slot-scope="props"
            :href="urls['occurrence_get'].replace('occurrence_id', props.row.id)"
            class="greek"
            v-html="props.row.incipit"
        />
        <a
            v-if="props.row.manuscript"
            slot="manuscript"
            slot-scope="props"
            :href="urls['manuscript_get'].replace('manuscript_id', props.row.manuscript.id)"
        >
          {{ props.row.manuscript.name }} ({{ props.row.location }})
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
              :href="urls['occurrence_edit'].replace('occurrence_id', props.row.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>
          <a
              :href="urls['occurrence_edit'].replace('occurrence_id', props.row.id) + '?clone=1'"
              class="action"
              title="Duplicate"
          >
            <i class="fa fa-files-o" />
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
import Vue from 'vue/dist/vue.js';;
import VueFormGenerator from 'vue-form-generator';
import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import {
  formatDate, greekFont
} from '@/helpers/formatUtil';

import AbstractSearch from '../mixins/AbstractSearch';
import axios from 'axios';

import AbstractListEdit from '../mixins/AbstractListEdit';

import fieldRadio from '../Components/FormFields/fieldRadio.vue';
import ActiveFilters from '../Components/Search/ActiveFilters.vue';
import {useSearchSession} from "../composables/useSearchSession";

import PersistentConfig from "@/mixins/PersistentConfig";


Vue.component('FieldRadio', fieldRadio);

export default {
  components: { ActiveFilters },
  mixins: [
    AbstractSearch,
    PersistentConfig('OccurrenceSearchConfig'),
  ],
  data() {
    const data = {
      urls: window.urls || {},
      model: {
        text_mode: ['greek'],
        comment_mode: ['latin'],
        date_search_type: 'exact',
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
        manuscript_content: [],
        manuscript_content_op: 'or',
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
        sortable: ['id', 'incipit', 'manuscript', 'date', 'created', 'modified'],
        customFilters: ['filters'],
        requestFunction: AbstractSearch.requestFunction,
        rowClassCallback(row) {
          return (row.public == null || row.public) ? '' : 'warning';
        },
      },
      submitModel: {
        submitType: 'occurrence',
        occurrence: {},
      },
      defaultOrdering: 'incipit',
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
    data.schema.fields.year_from = {
      type: 'input',
      inputType: 'number',
      label: 'Year from',
      labelClasses: 'control-label',
      model: 'year_from',
      min: AbstractSearch.YEAR_MIN,
      max: AbstractSearch.YEAR_MAX,
      validator: VueFormGenerator.validators.number,
    };
    data.schema.fields.year_to = {
      type: 'input',
      inputType: 'number',
      label: 'Year to',
      labelClasses: 'control-label',
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
    [data.schema.fields.manuscript_content_op, data.schema.fields.manuscript_content] = createMultiMultiSelect(
        'Manuscript Content',
        {
          model: 'manuscript_content',
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
    data.schema.fields.dbbe = createMultiSelect(
        'Transcribed by DBBE',
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
    data.schema.fields.id = createMultiSelect('DBBE ID', { model: 'id' });
    data.schema.fields.prev_id = createMultiSelect('Former DBBE ID', { model: 'prev_id' });
    if (this.isViewInternal) {
      data.schema.fields.text_status = createMultiSelect(
          'Text Status',
          {
            model: 'text_status',
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
  },
  mounted(){
    this.session.setupCollapsibleLegends();
    this.$on('config-changed', this.session.handleConfigChange(this.schema));
  },
  computed: {
    depUrls() {
      return {
        Types: {
          depUrl: this.urls.type_deps_by_occurrence.replace('occurrence_id', this.submitModel.occurrence.id),
          url: this.urls.type_get,
          urlIdentifier: 'type_id',
        },
      };
    },
    tableColumns() {
      const columns = ['id', 'incipit', 'manuscript', 'date'];
      if (this.textSearch) {
        columns.unshift('text');
      }
      if (this.commentSearch) {
        columns.unshift('comment');
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
  methods: {
    greekFont,
    formatDate,
    del(row) {
      this.submitModel.occurrence = {
        id: row.id,
        name: row.incipit,
      };
      AbstractListEdit.methods.deleteDependencies.call(this);
    },
    submitDelete() {
      this.openRequests += 1;
      this.deleteModal = false;
      axios.delete(this.urls.occurrence_delete.replace('occurrence_id', this.submitModel.occurrence.id))
          .then(() => {
            // Don't create a new history item
            this.noHistory = true;
            this.$refs.resultTable.refresh();
            this.openRequests -= 1;
            this.alerts.push({ type: 'success', message: 'Occurrence deleted successfully.' });
          })
          .catch((error) => {
            this.openRequests -= 1;
            this.alerts.push({ type: 'error', message: 'Something went wrong while deleting the occurrence.' });
            console.error(error);
          });
    },
  },
};
</script>
