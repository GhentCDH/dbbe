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
            ref="occRef"
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
          @deletedActiveFilter="handleDeletedActiveFilter"
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
          ref="resultTableRef"
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
      <!--          <div style="position: relative; height: 100px;">-->
      <!--            <button @click="downloadCSVHandler"-->
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
    <Delete
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

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import qs from 'qs';
import VueTables from 'vue-tables-2';

import Delete from '../Components/Edit/Modals/Delete.vue';
import Alerts from "@/Components/Alerts.vue";
import ActiveFilters from '../Components/Search/ActiveFilters.vue';
import CollectionManager from '../Components/Search/CollectionManager.vue';
import { nextTick } from 'vue';

import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import { formatDate, greekFont, YEAR_MAX, YEAR_MIN } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";
import { downloadCSV } from "@/helpers/downloadUtil";
import { axiosGet, cleanParams } from "@/helpers/searchAppHelpers/requestFunctionUtil";
import { constructFilterValues } from "@/helpers/searchAppHelpers/filterUtil";
import { popHistory, pushHistory } from "@/helpers/searchAppHelpers/historyUtil";
import { fetchDependencies } from "@/helpers/fetchDependencies";

import { useRequestTracker } from "@/composables/searchAppComposables/useRequestTracker";
import { usePaginationCount } from "@/composables/searchAppComposables/usePaginationCount";
import { useFormValidation } from "@/composables/searchAppComposables/useFormValidation";
import { useSearchFields } from "@/composables/searchAppComposables/useSearchFields";
import { useCollectionManagement } from "@/composables/searchAppComposables/useCollectionManagement";
import { useSearchSession} from "@/composables/searchAppComposables/useSearchSession";
import validatorUtil from "@/helpers/validatorUtil";
const props = defineProps({
  isEditor: {
    type: Boolean,
    default: false,
  },
  isViewInternal: {
    type: Boolean,
    default: false,
  },
  initUrls: {
    type: String,
    default: '',
  },
  initData: {
    type: String,
    default: '',
  },
  initManagements: {
    type: String,
    default: '',
  },
});

// Define emits
const emit = defineEmits();

// Parse props
const urls = JSON.parse(props.initUrls || '{}');
const data = JSON.parse(props.initData || '{}');
const managements = JSON.parse(props.initManagements || '{}');

// Form options
const formOptions = ref({
  validateAfterLoad: true,
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
});

// Model
const model = ref({
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
});

// Add internal fields if needed
if (props.isViewInternal) {
  model.value.text_stem = 'original';
}

const originalModel = ref({});

// Schema
const schema = ref({
  fields: {},
});

// Build schema fields
const buildSchema = () => {
  const fields = {};

  // Text mode and search fields
  fields.text_mode = createLanguageToggle('text');
  fields.text = {
    type: 'input',
    inputType: 'text',
    styleClasses: 'greek',
    labelClasses: 'control-label',
    label: 'Text',
    model: 'text',
  };

  if (props.isViewInternal) {
    fields.text_stem = {
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

  fields.text_combination = {
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

  fields.text_fields = {
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

  // Date fields
  fields.year_from = {
    type: 'input',
    inputType: 'number',
    label: 'Year from',
    labelClasses: 'control-label',
    model: 'year_from',
    min: YEAR_MIN,
    max: YEAR_MAX,
    validator: validatorUtil.number,
  };

  fields.year_to = {
    type: 'input',
    inputType: 'number',
    label: 'Year to',
    labelClasses: 'control-label',
    model: 'year_to',
    min: YEAR_MIN,
    max: YEAR_MAX,
    validator: validatorUtil.number,
  };

  fields.date_search_type = {
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

  // Multi-select fields
  fields.person = createMultiSelect('Person', {}, {
    multiple: true,
    closeOnSelect: false,
  });

  fields.role = createMultiSelect('Role', {
    dependency: 'person',
  }, {
    multiple: true,
    closeOnSelect: false,
  });

  [fields.metre_op, fields.metre] = createMultiMultiSelect('Metre');
  [fields.genre_op, fields.genre] = createMultiMultiSelect('Genre');
  [fields.subject_op, fields.subject] = createMultiMultiSelect('Subject');
  [fields.manuscript_content_op, fields.manuscript_content] = createMultiMultiSelect(
      'Manuscript Content',
      { model: 'manuscript_content' }
  );

  // Comment fields
  fields.comment_mode = createLanguageToggle('comment');
  fields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    labelClasses: 'control-label',
    model: 'comment',
    validator: validatorUtil.string,
  };

  // Additional fields
  fields.dbbe = createMultiSelect('Transcribed by DBBE', {
    model: 'dbbe',
  }, {
    customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
  });

  [fields.acknowledgement_op, fields.acknowledgement] = createMultiMultiSelect(
      'Acknowledgements',
      { model: 'acknowledgement' }
  );

  fields.id = createMultiSelect('DBBE ID', { model: 'id' });
  fields.prev_id = createMultiSelect('Former DBBE ID', { model: 'prev_id' });

  // Internal only fields
  if (props.isViewInternal) {
    fields.text_status = createMultiSelect('Text Status', {
      model: 'text_status',
      styleClasses: 'has-warning',
    });

    fields.public = createMultiSelect('Public', {
      styleClasses: 'has-warning',
    }, {
      customLabel: ({ _id, name }) => (name === 'true' ? 'Public only' : 'Internal only'),
    });

    fields.management = createMultiSelect('Management collection', {
      model: 'management',
      styleClasses: 'has-warning',
    });

    fields.management_inverse = {
      type: 'checkbox',
      styleClasses: 'has-warning',
      label: 'Inverse management collection selection',
      labelClasses: 'control-label',
      model: 'management_inverse',
    };
  }

  schema.value.fields = fields;
};

// Build schema on initialization
buildSchema();

// Table options
const tableOptions = ref({
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
  rowClassCallback(row) {
    return (row.public == null || row.public) ? '' : 'warning';
  },
});

// Submit model
const submitModel = reactive({
  submitType: 'occurrence',
  occurrence: {},
});

// Refs
const defaultOrdering = ref('incipit');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const resultTableRef = ref(null);
const aggregation = ref({});
const historyRequest = ref(false);
const occRef = ref(null);
const deleteModal = ref(false);
const delDependencies = ref({});

// Computed properties
const fields = computed(() => {
  const res = {};
  if (schema.value && schema.value.fields) {
    Object.values(schema.value.fields).forEach(field => {
      if (!field.multiple || field.multi === true) {
        res[field.model] = field;
      }
    });
  }
  return res;
});

const depUrls = computed(() => ({
  Types: {
    depUrl: urls.type_deps_by_occurrence?.replace('occurrence_id', submitModel.occurrence.id) || '',
    url: urls.type_get || '',
    urlIdentifier: 'type_id',
  },
}));

const tableColumns = computed(() => {
  const columns = ['id', 'incipit', 'manuscript', 'date'];
  if (textSearch.value) {
    columns.unshift('text');
  }
  if (commentSearch.value) {
    columns.unshift('comment');
  }
  if (props.isViewInternal) {
    columns.push('created', 'modified', 'actions', 'c');
  }
  return columns;
});

// Use composables
const { countRecords, updateCountRecords } = usePaginationCount(resultTableRef);

const {
  openRequests,
  alerts,
  startRequest,
  endRequest,
} = useRequestTracker();

const {
  onValidated,
  lastChangedField,
  actualRequest,
  initFromURL
} = useFormValidation({
  model,
  fields,
  resultTableRef,
  defaultOrdering,
  emitFilter: (filters) => VueTables.Event.$emit('vue-tables.filter::filters', filters),
  historyRequest
});

const {
  notEmptyFields,
  changeTextMode,
  setUpOperatorWatchers,
  onLoaded,
  deleteActiveFilter,
  onDataExtend,
  commentSearch,
  textSearch
} = useSearchFields(model, schema, fields, aggregation, {
  multiple: true,
  updateCountRecords,
  initFromURL,
  endRequest,
  historyRequest
});

const { init, onData, setupCollapsibleLegends } = useSearchSession({
  urls,
  data,
  aggregation,
  emit,
  elRef: occRef,
  onDataExtend
}, 'OccurrenceSearchConfig');

const {
  collectionArray,
  collectionToggleAll,
  clearCollection,
  addManagementsToSelection,
  removeManagementsFromSelection,
  addManagementsToResults,
  removeManagementsFromResults,
} = useCollectionManagement({
  data,
  urls,
  constructFilterValues,
  resultTableRef,
  alerts,
  startRequest,
  endRequest,
  noHistory
});

// Functions
const handleDeletedActiveFilter = (field) => {
  deleteActiveFilter(field);
  onValidated(true);
};

const requestFunction = async (data) => {
  const params = cleanParams(data);
  startRequest();
  let url = urls['occurrences_search_api'];

  if (!initialized || !actualRequest) {
    if (!initialized) {
      onData(data);
    }
    return {
      data: {
        data: initialized ? data : data.data,
        count: initialized ? count : data.count,
      },
    };
  }

  if (historyRequest.value) {
    if (historyRequest !== 'init') {
      url = `${url}?${historyRequest}`;
    }
    return await axiosGet(url);
  }

  if (!noHistory) {
    pushHistory(params, model, originalModel, fields, tableOptions);
  } else {
    noHistory.value = false;
  }

  return await axiosGet(url, { params, paramsSerializer: qs.stringify }, tableCancel, openRequests, alerts, onData, data);
};

const del = async (row) => {
  submitModel.occurrence = {
    id: row.id,
    name: row.incipit,
  };

  startRequest();
  const depUrlsEntries = Object.entries(depUrls.value);

  try {
    delDependencies.value = await fetchDependencies(depUrlsEntries);
    deleteModal.value = true;
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while checking for dependencies.',
      login: isLoginError(error),
    });
    console.error(error);
  } finally {
    endRequest();
  }
};

const submitDelete = async () => {
  startRequest();
  deleteModal.value = false;

  try {
    await axios.delete(
        urls.occurrence_delete.replace('occurrence_id', submitModel.occurrence.id)
    );
    noHistory.value = true;
    resultTableRef.value?.refresh();
    alerts.value.push({ type: 'success', message: 'Occurrence deleted successfully.' });
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while deleting the occurrence.' });
    console.error(error);
  } finally {
    endRequest();
  }
};

const modelUpdated = (fieldName) => {
  lastChangedField.value = fieldName;
};

const resetAllFilters = () => {
  model.value = JSON.parse(JSON.stringify(originalModel));
  onValidated(true);
};

const downloadCSVHandler = async () => {
  try {
    await downloadCSV(urls);
  } catch (error) {
    console.error(error);
    alerts.value.push({ type: 'error', message: 'Error downloading CSV.' });
  }
};

// Watchers
watch(() => model.value.text_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'text');
});

watch(() => model.value.comment_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'comment');
});
watch(
    () => schema.value?.groups,
    async (groups) => {
      if (!groups || !Array.isArray(groups)) return;
      await nextTick();
      const legends = occRef.value?.$el?.querySelectorAll('.vue-form-generator .collapsible legend') || [];
      if (legends.length > 0) {
        setupCollapsibleLegends(schema);
      }
    },
    { immediate: true }
);


// Setup table options request function
tableOptions.value.requestFunction = requestFunction;

// Setup operator watchers
setUpOperatorWatchers();

// Mounted lifecycle
onMounted(() => {
  updateCountRecords();
  initFromURL(aggregation.value);
  originalModel.value = JSON.parse(JSON.stringify(model.value));
  window.onpopstate = (event) => {
    historyRequest.value = popHistory();
    resultTableRef.value?.refresh();
  };
  updateCountRecords();
});
</script>