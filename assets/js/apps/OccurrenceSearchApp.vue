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
      <div>

        <div
            v-if="countRecords"
            class="count-records d-flex justify-content-end mb-2"        >
          <h6>{{ countRecords }}</h6>
        </div>
        <div>
          <div class="per-page-container">
            <label class="me-2">Records:</label>
            <BSelect
                id="perPageSelect"
                label=""
                :selected="currentPerPage"
                :options="perPageOptions"
                @update:selected="updatePerPage"
            />
        </div>
        </div>

      </div>
      <div
          v-if="isViewInternal"
          class="per-page-container"
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


      <!-- Custom BTable -->
      <BTable
          :items="tableData"
          :fields="tableFields"
          :sort-by="sortBy"
          :sort-ascending="sortAscending"
          :sort-icon="{
            base: 'fa',
            up: 'fa-chevron-up',
            down: 'fa-chevron-down',
            is: 'fa-sort'
          }"
          @sort="handleSort"
      >
        <!-- Text column slot -->
        <template #text="{ item }">
          <span class="greek">
            <template v-if="item.title">
              <ol type="A">
                <li
                    v-for="(titleItem, index) in item.title"
                    :key="index"
                    value="20"
                    v-html="titleItem"
                />
              </ol>
            </template>
            <template v-if="item.text">
              <ol>
                <li
                    v-for="(textItem, index) in item.text"
                    :key="index"
                    :value="Number(index) + 1"
                    v-html="textItem"
                />
              </ol>
            </template>
          </span>
        </template>

        <!-- Comment column slot -->
        <template #comment="{ item }">
          <template v-if="item.palaeographical_info">
            <em>Palaeographical info</em>
            <ol>
              <li
                  v-for="(infoItem, index) in item.palaeographical_info"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(infoItem)"
              />
            </ol>
          </template>
          <template v-if="item.contextual_info">
            <em>Contextual info</em>
            <ol>
              <li
                  v-for="(infoItem, index) in item.contextual_info"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(infoItem)"
              />
            </ol>
          </template>
          <template v-if="item.public_comment">
            <em v-if="isViewInternal">Public comment</em>
            <em v-else>Comment</em>
            <ol>
              <li
                  v-for="(commentItem, index) in item.public_comment"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(commentItem)"
              />
            </ol>
          </template>
          <template v-if="item.private_comment">
            <em>Private comment</em>
            <ol>
              <li
                  v-for="(commentItem, index) in item.private_comment"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(commentItem)"
              />
            </ol>
          </template>
        </template>

        <!-- ID column slot -->
        <template #id="{ item }">
          <a :href="urls['occurrence_get'].replace('occurrence_id', item.id)">
            {{ item.id }}
          </a>
        </template>

        <!-- Incipit column slot -->
        <template #incipit="{ item }">
          <a
              :href="urls['occurrence_get'].replace('occurrence_id', item.id)"
              class="greek"
              v-html="item.incipit"
          />
        </template>

        <!-- Manuscript column slot -->
        <template #manuscript="{ item }">
          <a
              v-if="item.manuscript"
              :href="urls['manuscript_get'].replace('manuscript_id', item.manuscript.id)"
          >
            {{ item.manuscript.name }} ({{ item.location }})
          </a>
        </template>

        <!-- Date column slot -->
        <template #date="{ item }">
          <template v-if="item.date_floor_year && item.date_ceiling_year">
            <template v-if="item.date_floor_year === item.date_ceiling_year">
              {{ item.date_floor_year }}
            </template>
            <template v-else>
              {{ item.date_floor_year }} - {{ item.date_ceiling_year }}
            </template>
          </template>
        </template>

        <!-- Created column slot -->
        <template #created="{ item }">
          {{ formatDate(item.created) }}
        </template>

        <!-- Modified column slot -->
        <template #modified="{ item }">
          {{ formatDate(item.modified) }}
        </template>

        <!-- Actions column slot (internal view only) -->
        <template #actions="{ item }">
          <a
              :href="urls['occurrence_edit'].replace('occurrence_id', item.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>
          <a
              :href="urls['occurrence_edit'].replace('occurrence_id', item.id) + '?clone=1'"
              class="action"
              title="Duplicate"
          >
            <i class="fa fa-files-o" />
          </a>
          <a
              href="#"
              class="action"
              title="Delete"
              @click.prevent="del(item)"
          >
            <i class="fa fa-trash-o" />
          </a>
        </template>

        <!-- Checkbox column slot (internal view only) -->
        <template #c="{ item }">
          <span class="checkbox checkbox-primary">
            <input
                :id="item.id"
                v-model="collectionArray"
                :name="item.id"
                :value="item.id"
                type="checkbox"
            >
            <label :for="item.id" />
          </span>
        </template>
      </BTable>

      <div class="mt-3 text-center">
        <BPagination
            v-if="totalRecords > 0"
            :total-records="totalRecords"
            :page="currentPage"
            :per-page="currentPerPage"
            @update:page="updatePage"
        />
      </div>
      <div
          v-if="isViewInternal"
          class="per-page-container"      >
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

// Import your custom components
import BTable from "@/components/BTable.vue";
import BSelect from "@/components/BSelect.vue";
import BPagination from "@/components/BPagination.vue";
import Delete from '../components/Edit/Modals/Delete.vue';
import Alerts from "@/components/Alerts.vue";
import ActiveFilters from '../components/Search/ActiveFilters.vue';
import CollectionManager from '../components/Search/CollectionManager.vue';
import { nextTick } from 'vue';

import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import { formatDate, greekFont, YEAR_MAX, YEAR_MIN } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";
import { downloadCSV } from "@/helpers/downloadUtil";
import { constructFilterValues } from "@/helpers/searchAppHelpers/filterUtil";
import { popHistory, pushHistory } from "@/helpers/searchAppHelpers/historyUtil";
import { fetchDependencies } from "@/helpers/searchAppHelpers/fetchDependencies";

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
const aggregation = ref({});

// Parse props
const urls = JSON.parse(props.initUrls || '{}');
const data = JSON.parse(props.initData || '{}');
const managements = JSON.parse(props.initManagements || '{}');

// Add pagination and sorting state
const currentPage = ref(1);
const currentPerPage = ref(25);
const totalRecords = ref(0);
const sortBy = ref('incipit');
const sortAscending = ref(true);
const tableData = ref([]);

// Schema initialization state
const schemaInitialized = ref(false);

// Per page options
const perPageOptions = [
  { value: 25, text: '25' },
  { value: 50, text: '50' },
  { value: 100, text: '100' }
];

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

// Schema - start with basic structure
const schema = ref({
  fields: {},
});

// Build initial schema fields - function that returns fields
const buildInitialSchema = () => {
  const schemaFields = {};

  // Text mode and search fields
  schemaFields.text_mode = createLanguageToggle('text');
  schemaFields.text = {
    type: 'input',
    inputType: 'text',
    styleClasses: 'greek',
    labelClasses: 'control-label',
    label: 'Text',
    model: 'text',
  };

  if (props.isViewInternal) {
    schemaFields.text_stem = {
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

  schemaFields.text_combination = {
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

  schemaFields.text_fields = {
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
  schemaFields.year_from = {
    type: 'input',
    inputType: 'number',
    label: 'Year from',
    labelClasses: 'control-label',
    model: 'year_from',
    min: YEAR_MIN,
    max: YEAR_MAX,
    validator: validatorUtil.number,
  };

  schemaFields.year_to = {
    type: 'input',
    inputType: 'number',
    label: 'Year to',
    labelClasses: 'control-label',
    model: 'year_to',
    min: YEAR_MIN,
    max: YEAR_MAX,
    validator: validatorUtil.number,
  };

  schemaFields.date_search_type = {
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

  // Multi-select fields - these use aggregation data
  schemaFields.person = createMultiSelect('Person', {}, {
    multiple: true,
    closeOnSelect: false,
  });

  schemaFields.role = createMultiSelect('Role', {
    dependency: 'person',
  }, {
    multiple: true,
    closeOnSelect: false,
  });

  [schemaFields.metre_op, schemaFields.metre] = createMultiMultiSelect('Metre');
  [schemaFields.genre_op, schemaFields.genre] = createMultiMultiSelect('Genre');
  [schemaFields.subject_op, schemaFields.subject] = createMultiMultiSelect('Subject');
  [schemaFields.manuscript_content_op, schemaFields.manuscript_content] = createMultiMultiSelect(
      'Manuscript Content',
      { model: 'manuscript_content' }
  );

  // Comment fields
  schemaFields.comment_mode = createLanguageToggle('comment');
  schemaFields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    labelClasses: 'control-label',
    model: 'comment',
    validator: validatorUtil.string,
  };

  // Additional fields
  schemaFields.dbbe = createMultiSelect('Transcribed by DBBE', {
    model: 'dbbe',
  }, {
    customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
  });

  [schemaFields.acknowledgement_op, schemaFields.acknowledgement] = createMultiMultiSelect(
      'Acknowledgements',
      { model: 'acknowledgement' }
  );

  schemaFields.id = createMultiSelect('DBBE ID', { model: 'id' });
  schemaFields.prev_id = createMultiSelect('Former DBBE ID', { model: 'prev_id' });

  // Internal only fields
  if (props.isViewInternal) {
    schemaFields.text_status = createMultiSelect('Text Status', {
      model: 'text_status',
      styleClasses: 'has-warning',
    });

    schemaFields.public = createMultiSelect('Public', {
      styleClasses: 'has-warning',
    }, {
      customLabel: ({ _id, name }) => (name === 'true' ? 'Public only' : 'Internal only'),
    });

    schemaFields.management = createMultiSelect('Management collection', {
      model: 'management',
      styleClasses: 'has-warning',
    });

    schemaFields.management_inverse = {
      type: 'checkbox',
      styleClasses: 'has-warning',
      label: 'Inverse management collection selection',
      labelClasses: 'control-label',
      model: 'management_inverse',
    };
  }

  return schemaFields;
};

// Initialize schema with initial fields
schema.value.fields = buildInitialSchema();

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
const historyRequest = ref(false);
const occRef = ref(null);
const deleteModal = ref(false);
const delDependencies = ref({});

// Computed properties
const fields = computed(() => {
  const res = {};
  if (schema.value?.fields) {
    Object.values(schema.value.fields).forEach(field => {
      if (!field.multiple || field.multi === true) {
        res[field.model] = field;
      }
    });
  }
  return res;
});

const depUrls = computed(() => {
  if (!submitModel.occurrence?.id || !urls.type_deps_by_occurrence || !urls.type_get) {
    return {};
  }

  return {
    Types: {
      depUrl: urls.type_deps_by_occurrence.replace('occurrence_id', submitModel.occurrence.id),
      url: urls.type_get,
      urlIdentifier: 'type_id',
    },
  };
});

// Table fields configuration
const tableFields = computed(() => {
  if (!schema.value?.fields || !initialized.value) {
    return [];
  }

  const fields = [
    { key: 'id', label: 'ID', sortable: true, thClass: 'no-wrap' }
  ];

  // Add text column if text search is active
  if (textSearch.value) {
    fields.unshift({
      key: 'text',
      label: 'Title (T.) / text (matching verses only)',
      sortable: false
    });
  }

  // Add comment column if comment search is active
  if (commentSearch.value) {
    fields.unshift({
      key: 'comment',
      label: 'Comment (matching lines only)',
      sortable: false
    });
  }

  fields.push(
      { key: 'incipit', label: 'Incipit', sortable: true },
      { key: 'manuscript', label: 'Manuscript', sortable: true },
      { key: 'date', label: 'Date', sortable: true }
  );

  // Add internal view columns
  if (props.isViewInternal) {
    fields.push(
        { key: 'created', label: 'Created', sortable: true },
        { key: 'modified', label: 'Modified', sortable: true },
        { key: 'actions', label: 'Actions', sortable: false },
        { key: 'c', label: '', sortable: false }
    );
  }

  return fields;
});

const countRecords = computed(() => {
  if (totalRecords.value === 0) return '';
  const start = (currentPage.value - 1) * currentPerPage.value + 1;
  const end = Math.min(currentPage.value * currentPerPage.value, totalRecords.value);
  return `Showing ${start} to ${end} of ${totalRecords.value} entries`;
});

// Use composables that don't depend on loadData first
const {
  openRequests,
  alerts,
  startRequest,
  endRequest,
  cleanParams,
  handleError,
  axiosGet
} = useRequestTracker();

// Initialize useSearchFields with schema and fields - it can modify the schema through the fields computed
const {
  notEmptyFields,
  changeTextMode,
  setUpOperatorWatchers,
  deleteActiveFilter,
  onDataExtend,
  commentSearch,
  textSearch,
  onLoaded
} = useSearchFields(model, schema, fields, aggregation, {
  multiple: true,
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

const handleSort = ({ sortBy: newSortBy, sortAscending: newSortAscending }) => {
  sortBy.value = newSortBy;
  sortAscending.value = newSortAscending;
  currentPage.value = 1; // Reset to first page when sorting
  loadData(true); // Force request for sorting
};

const updatePage = (newPage) => {
  currentPage.value = newPage;
  loadData(true); // Force request for pagination
};

const updatePerPage = (newPerPage) => {
  currentPerPage.value = parseInt(newPerPage);
  currentPage.value = 1; // Reset to first page
  loadData(true); // Force request for per-page change
};

const loadData = async (forcedRequest = false) => {
  console.log('loadData called with forcedRequest:', forcedRequest, 'initialized:', initialized.value, 'actualRequest:', actualRequest.value);

  // Ensure initialization is complete before proceeding
  if (!initialized.value) {
    if (data && data.data) {
      onData(data);
      tableData.value = data.data || [];
      totalRecords.value = data.count || 0;
      initialized.value = true;
    }
    return;
  }

  // Make request if validation has been triggered OR it's a forced request OR we have actual filters
  const shouldMakeRequest = actualRequest.value || forcedRequest || hasActiveFilters();

  console.log('Should make request:', shouldMakeRequest);

  if (!shouldMakeRequest) {
    console.log('Skipping request - no validation trigger, not forced, and no active filters');
    return;
  }

  // Ensure all required values are available
  if (!model.value || !fields.value || !urls['occurrences_search_api']) {
    console.log('Missing required values for request');
    return;
  }

  // Build parameters in the expected format with filters nested
  const filterParams = {};

  // Extract filter parameters from model
  Object.entries(model.value).forEach(([key, value]) => {
    if (value != null && value !== '' &&
        !(Array.isArray(value) && value.length === 0)) {

      // Handle array values like text_mode, comment_mode
      if (Array.isArray(value) && value.length > 0) {
        if (key.endsWith('_mode')) {
          filterParams[key] = value[0]; // Take first value for mode fields
        } else {
          // For multi-select fields, send as array but extract IDs if objects
          filterParams[key] = value.map(item =>
              typeof item === 'object' && item.id ? item.id : item
          );
        }
      } else if (typeof value === 'object' && value.id) {
        // Handle single object with id
        filterParams[key] = value.id;
      } else {
        filterParams[key] = value;
      }
    }
  });

  // Build final parameters with filters nested and pagination/sorting separate
  const params = {
    page: currentPage.value,
    limit: currentPerPage.value,
    orderBy: sortBy.value,
    ascending: sortAscending.value ? 1 : 0,
    filters: filterParams
  };

  console.log('Making request with params:', params);

  startRequest();
  let url = urls['occurrences_search_api'];

  try {
    if (historyRequest.value) {
      if (historyRequest.value !== 'init') {
        url = `${url}?${historyRequest.value}`;
      }
      const response = await axiosGet(url, {}, tableCancel, onData, data);
      tableData.value = response?.data?.data || [];
      totalRecords.value = response?.data?.count || 0;
    } else {
      if (!noHistory.value) {
        pushHistory(params, model, originalModel, fields, defaultOrdering.value, 25);
      } else {
        noHistory.value = false;
      }

      const response = await axiosGet(
          url,
          {
            params,
            paramsSerializer: qs.stringify
          },
          tableCancel,
          onData,
          data
      );
      tableData.value = response?.data?.data || [];
      totalRecords.value = response?.data?.count || 0;
    }

    console.log('Request completed successfully, got', totalRecords.value, 'records');
  } catch (error) {
    console.error('Request failed:', error);
    handleError(error);
    tableData.value = [];
    totalRecords.value = 0;
  } finally {
    endRequest();
  }
};

// Helper function to check if we have active filters
const hasActiveFilters = () => {
  if (!model.value) return false;

  // Check for any non-empty filter values
  for (const [key, value] of Object.entries(model.value)) {
    if (value != null && value !== '' &&
        !(Array.isArray(value) && value.length === 0)) {
      // Skip default/initial values that shouldn't trigger filtering
      if (key === 'text_mode' && JSON.stringify(value) === JSON.stringify(['greek'])) continue;
      if (key === 'comment_mode' && JSON.stringify(value) === JSON.stringify(['latin'])) continue;
      if (key === 'date_search_type' && value === 'exact') continue;
      if (key === 'text_fields' && value === 'text') continue;
      if (key === 'text_combination' && value === 'all') continue;
      if (key === 'text_stem' && value === 'original') continue;
      if (key.endsWith('_op') && value === 'or') continue;

      console.log('Found active filter:', key, '=', value);
      return true;
    }
  }

  return false;
};

const del = async (row) => {
  if (!row?.id || !row?.incipit) {
    console.error('Row missing required properties:', row);
    return;
  }

  submitModel.occurrence = {
    id: row.id,
    name: row.incipit,
  };

  startRequest();

  try {
    const depUrlsEntries = Object.entries(depUrls.value);

    if (depUrlsEntries.length > 0) {
      delDependencies.value = await fetchDependencies(depUrlsEntries);
    } else {
      delDependencies.value = {};
    }

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
  if (!submitModel.occurrence?.id || !urls.occurrence_delete) {
    console.error('Missing occurrence ID or delete URL');
    return;
  }

  startRequest();
  deleteModal.value = false;

  try {
    await axios.delete(
        urls.occurrence_delete.replace('occurrence_id', submitModel.occurrence.id)
    );
    noHistory.value = true;
    loadData(true); // Force request after deletion
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
  if (!originalModel.value || Object.keys(originalModel.value).length === 0) {
    console.warn('Original model not initialized, using current model structure');
    originalModel.value = JSON.parse(JSON.stringify(model.value));
  }

  model.value = JSON.parse(JSON.stringify(originalModel.value));
  onValidated(true);
};

const downloadCSVHandler = async () => {
  if (!urls || Object.keys(urls).length === 0) {
    console.error('URLs not initialized');
    alerts.value.push({ type: 'error', message: 'Download not available - URLs not initialized.' });
    return;
  }

  try {
    await downloadCSV(urls);
  } catch (error) {
    console.error(error);
    alerts.value.push({ type: 'error', message: 'Error downloading CSV.' });
  }
};

// Initialize form validation AFTER loadData is defined
const {
  onValidated,
  lastChangedField,
  actualRequest,
  initFromURL
} = useFormValidation({
  model,
  fields,
  defaultOrdering,
  historyRequest,
  currentPage,
  sortBy,
  sortAscending,
  onDataRefresh: loadData
});

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

watch(() => model.value.text, (newValue) => {
  if (newValue && newValue.trim().length > 0) {
    textSearch.value = true;
  } else {
    textSearch.value = false;
  }
}, { immediate: true });

watch(() => model.value.comment, (newValue) => {
  if (newValue && newValue.trim().length > 0) {
    commentSearch.value = true;
  } else {
    commentSearch.value = false;
  }
}, { immediate: true });

// Watch for form validation changes
watch(() => actualRequest.value, (newVal) => {
  if (newVal && initialized.value) {
    currentPage.value = 1; // Reset to first page on new search
    loadData();
  }
});

// Setup operator watchers
if (setUpOperatorWatchers) {
  setUpOperatorWatchers();
}

// Mounted lifecycle
onMounted(async () => {
  // Initialize original model first
  originalModel.value = JSON.parse(JSON.stringify(model.value));

  // Initial data setup
  if (data && data.data) {
    tableData.value = data.data;
    totalRecords.value = data.count || 0;
    initialized.value = true;

    // Process initial data
    if (onData) {
      onData(data);
    }
  }

  // Call onLoaded from useSearchFields to let it update the schema with aggregation data
  if (onLoaded && aggregation.value) {
    try {
      await onLoaded(aggregation.value);
    } catch (error) {
      console.error('Error in onLoaded:', error);
    }
  }

  // Initialize from URL parameters - wait for next tick to ensure DOM is ready
  await nextTick();

  if (initFromURL && aggregation.value) {
    try {
      initFromURL(aggregation.value);
    } catch (error) {
      console.error('Error initializing from URL:', error);
    }
  }

  // Setup history handling
  window.onpopstate = (event) => {
    if (popHistory) {
      historyRequest.value = popHistory();
      if (initialized.value) {
        loadData(true); // Force request for history navigation
      }
    }
  };
});
</script>

<style scoped>
.table {
  width: 100%;
  margin-bottom: 1rem;
  background-color: transparent;
  border-collapse: collapse;
}

.table th,
.table td {
  padding: 0.75rem;
  vertical-align: top;
  border-top: 1px solid #dee2e6;
}

.table thead th {
  vertical-align: bottom;
  border-bottom: 2px solid #dee2e6;
  background-color: #f8f9fa;
}

.table th.sortable {
  cursor: pointer;
  user-select: none;
}

.table th.sortable:hover {
  background-color: #e9ecef;
}

.table tr.warning {
  background-color: #fff3cd;
}

.greek {
  font-family: "Times New Roman", Times, serif;
}

.no-wrap {
  white-space: nowrap;
}

.action {
  margin-right: 0.5rem;
  color: #007bff;
  text-decoration: none;
}

.action:hover {
  color: #0056b3;
  text-decoration: underline;
}

.checkbox {
  display: inline-block;
  position: relative;
}

.checkbox input[type="checkbox"] {
  margin: 0;
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter, .fade-leave-to {
  opacity: 0;
}

.collection-select-all {
  margin: 1rem 0;
  text-align: center;
}

.collection-select-all a {
  color: #007bff;
  text-decoration: none;
  margin: 0 0.25rem;
}

.collection-select-all a:hover {
  text-decoration: underline;
}

.count-records {
  margin-bottom: 1rem;
}

.count-records h6 {
  color: #6c757d;
  font-weight: normal;
}

/* Table header styling */
.table th.sortable {
  cursor: pointer;
  user-select: none;
  position: relative;
}

.table th.sortable:hover {
  background-color: #e9ecef;
}

/* Sort icon spacing */
.sortable .heading-label {
  margin-right: 1rem;
}
.per-page-container {
  display: flex;
  justify-content: right;   /* centers the whole group horizontally */
  align-items: center;       /* vertical alignment */
  gap: 0.5rem;               /* space between items */
  margin-bottom: 1rem;
}

.per-page-container label {
  margin: 0;                 /* remove default label spacing */
}

.per-page-container select {
  width: auto;               /* make BSelect shrink to fit */
}

.collection-controls a {
  color: #007bff;
  text-decoration: none;
  margin: 0 0.25rem;
}

</style>