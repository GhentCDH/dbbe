<template>
  <div>
    <div class="col-xs-12">
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
    </div>
    <aside class="col-sm-3">
      <div class="bg-tertiary padding-default">
        <vue-form-generator
            ref="elRef"
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
        <div v-if="countRecords" class="count-records d-flex justify-content-end mb-2">
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
      <div v-if="isViewInternal" class="per-page-container">
        <a href="#" @click.prevent="clearCollection()">clear selection</a>
        |
        <a href="#" @click.prevent="collectionToggleAll()">(un)select all on this page</a>
      </div>

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
        <template #comment="{ item }">
          <template v-if="item.public_comment">
            <em v-if="isEditor">Public</em>
            <ol>
              <li v-for="(commentItem, index) in item.public_comment" :key="index" :value="Number(index) + 1" v-html="greekFont(commentItem)" />
            </ol>
          </template>
          <template v-if="item.private_comment">
            <em>Private</em>
            <ol>
              <li v-for="(commentItem, index) in item.private_comment" :key="index" :value="Number(index) + 1" v-html="greekFont(commentItem)" />
            </ol>
          </template>
        </template>

        <template #name="{ item }">
          <a :href="urls['manuscript_get'].replace('manuscript_id', item.id)">{{ item.name }}</a>
        </template>

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

        <template #content="{ item }">
          <template v-if="item.content">
            <!-- set displayContent using a v-for -->
            <div v-for="(displayContent, index) in [item.content.filter((content) => content['display'])]" :key="index">
              <ul v-if="displayContent.length > 1">
                <li v-for="(content, contentIndex) in displayContent" :key="contentIndex">
                  {{ content.name }}
                </li>
              </ul>
              <template v-else>
                {{ displayContent[0]?.name }}
              </template>
            </div>
          </template>
        </template>

        <template #occ="{ item }">
          {{ item.number_of_occurrences }}
        </template>

        <template #created="{ item }">
          {{ formatDate(item.created) }}
        </template>

        <template #modified="{ item }">
          {{ formatDate(item.modified) }}
        </template>

        <template #actions="{ item }">
          <a :href="urls['manuscript_edit'].replace('manuscript_id', item.id)" class="action" title="Edit">
            <i class="fa fa-pencil-square-o" />
          </a>
          <a href="#" class="action" title="Delete" @click.prevent="del(item)">
            <i class="fa fa-trash-o" />
          </a>
        </template>

        <template #c="{ item }">
          <span class="checkbox checkbox-primary">
            <input :id="item.id" v-model="collectionArray" :name="item.id" :value="item.id" type="checkbox">
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
      <div v-if="isViewInternal" class="per-page-container">
        <a href="#" @click.prevent="clearCollection()">clear selection</a>
        |
        <a href="#" @click.prevent="collectionToggleAll()">(un)select all on this page</a>
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
      <Alerts
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
      <div v-if="openRequests" class="loading-overlay">
        <div class="spinner" />
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch, nextTick } from 'vue';
import qs from 'qs';

import BTable from "@/components/SearchTable/BTable.vue";
import BSelect from "@/components/SearchTable/BSelect.vue";
import BPagination from "@/components/SearchTable/BPagination.vue";
import Delete from '../components/Edit/Modals/Delete.vue';
import Alerts from "@/components/Alerts.vue";
import ActiveFilters from '../components/SearchFilters/ActiveFilters.vue';
import CollectionManager from '../components/SearchFilters/CollectionManager.vue';

import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import { formatDate, greekFont, YEAR_MAX, YEAR_MIN } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";
import { downloadCSV } from "@/helpers/downloadUtil";
import { popHistory, pushHistory } from "@/helpers/searchAppHelpers/historyUtil";
import { fetchDependencies } from "@/helpers/searchAppHelpers/fetchDependencies";

import { useRequestTracker } from "@/composables/searchAppComposables/useRequestTracker";
import { useFormValidation } from "@/composables/searchAppComposables/useFormValidation";
import { useSearchFields } from "@/composables/searchAppComposables/useSearchFields";
import { useCollectionManagement } from "@/composables/searchAppComposables/useCollectionManagement";
import { useSearchSession } from "@/composables/searchAppComposables/useSearchSession";
import validatorUtil from "@/helpers/validatorUtil";
import {buildFilterParams} from "@/helpers/searchAppHelpers/filterUtil";

const props = defineProps({
  isEditor: { type: Boolean, default: false },
  isViewInternal: { type: Boolean, default: false },
  initUrls: { type: String, default: '' },
  initData: { type: String, default: '' },
  initIdentifiers: { type: String, default: '' },
  initManagements: { type: String, default: '' }
});

const emit = defineEmits();
const aggregation = ref({});

const urls = JSON.parse(props.initUrls || '{}');
const data = JSON.parse(props.initData || '{}');
const identifiers = JSON.parse(props.initIdentifiers || '[]');
const managements = JSON.parse(props.initManagements || '{}');

const currentPage = ref(1);
const currentPerPage = ref(25);
const totalRecords = ref(0);
const sortBy = ref('name');
const sortAscending = ref(true);
const tableData = ref([]);

const perPageOptions = [
  { value: 25, text: '25' },
  { value: 50, text: '50' },
  { value: 100, text: '100' }
];

const formOptions = ref({
  validateAfterLoad: true,
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success'
});

const model = ref({
  date_search_type: 'exact',
  person: [],
  role: [],
  content: [],
  content_op: 'or',
  origin: [],
  origin_op: 'or',
  comment_mode: ['latin'],
  acknowledgement: [],
  acknowledgement_op: 'or'
});

const originalModel = ref({});
const schema = ref({
  fields: {},
  groups: []
});

const buildInitialSchema = () => {
  const schemaFields = {};

  schemaFields.city = createMultiSelect('City');
  schemaFields.library = createMultiSelect('Library', { dependency: 'city' });
  schemaFields.collection = createMultiSelect('Collection', { dependency: 'library' });
  schemaFields.shelf = createMultiSelect('Shelf number', { model: 'shelf', dependency: 'collection' });

  schemaFields.year_from = {
    type: 'input',
    inputType: 'number',
    label: 'Year from',
    model: 'year_from',
    min: YEAR_MIN,
    max: YEAR_MAX,
    validator: validatorUtil.number,
  };

  schemaFields.year_to = {
    type: 'input',
    inputType: 'number',
    label: 'Year to',
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

  [schemaFields.content_op, schemaFields.content] = createMultiMultiSelect('Content');

  schemaFields.person = createMultiSelect('Person', {}, {
    multiple: true,
    closeOnSelect: false,
  });

  schemaFields.role = createMultiSelect('Role', { dependency: 'person' }, {
    multiple: true,
    closeOnSelect: false,
  });

  [schemaFields.origin_op, schemaFields.origin] = createMultiMultiSelect('Origin');

  schemaFields.comment_mode = createLanguageToggle('comment');

  schemaFields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    model: 'comment',
    validator: validatorUtil.string,
  };

  [schemaFields.acknowledgement_op, schemaFields.acknowledgement] = createMultiMultiSelect('Acknowledgements', {
    model: 'acknowledgement',
  });

  // Add identifier fields
  const idList = [];
  for (const identifier of identifiers) {
    idList.push(createMultiSelect(
        `${identifier.name} available?`,
        { model: `${identifier.systemName}_available` },
        { customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No') }
    ));
    idList.push(createMultiSelect(
        identifier.name,
        { dependency: `${identifier.systemName}_available`, model: identifier.systemName },
        { optionsLimit: 7000 }
    ));
  }

  if (props.isViewInternal) {
    schemaFields.public = createMultiSelect('Public', { styleClasses: 'has-warning' }, {
      customLabel: ({ _id, name }) => (name === 'true' ? 'Public only' : 'Internal only')
    });

    schemaFields.management = createMultiSelect('Management collection', {
      model: 'management',
      styleClasses: 'has-warning'
    });

    schemaFields.management_inverse = {
      type: 'checkbox',
      styleClasses: 'has-warning',
      label: 'Inverse management collection selection',
      labelClasses: 'control-label',
      model: 'management_inverse'
    };
  }

  const groups = [
    {
      styleClasses: 'collapsible collapsed',
      legend: 'External identifiers',
      fields: idList
    }
  ];

  return { fields: schemaFields, groups };
};

const submitModel = reactive({
  submitType: 'manuscript',
  manuscript: {}
});

const defaultOrdering = ref('name');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const historyRequest = ref(false);
const elRef = ref(null);
const deleteModal = ref(false);
const delDependencies = ref({});

const fields = computed(() => {
  const res = {};
  const addField = (field) => {
    if (!field.multiple || field.multi === true) {
      res[field.model] = field;
    }
  };

  if (schema.value?.fields) {
    Object.values(schema.value.fields).forEach(addField);
  }
  if (schema.value?.groups) {
    schema.value.groups.forEach(group => {
      if (group.fields) {
        group.fields.forEach(field => {
          res[field.model] = field;
        });
      }
    });
  }
  return res;
});

const depUrls = computed(() => {
  if (!submitModel.manuscript?.id || !urls.occurrence_deps_by_manuscript || !urls.occurrence_get) {
    return {};
  }
  return {
    Occurrences: {
      depUrl: urls.occurrence_deps_by_manuscript.replace('manuscript_id', submitModel.manuscript.id),
      url: urls.occurrence_get,
      urlIdentifier: 'occurrence_id'
    }
  };
});

const tableFields = computed(() => {
  if (!schema.value?.fields || !initialized.value) return [];

  const fields = [
    { key: 'name', label: 'Name', sortable: true }
  ];

  if (commentSearch.value) {
    fields.unshift({
      key: 'comment',
      label: 'Comment (matching lines only)',
      sortable: false
    });
  }

  fields.push(
      { key: 'date', label: 'Date', sortable: true },
      { key: 'content', label: 'Content', sortable: false }
  );

  if (props.isViewInternal) {
    fields.push(
        { key: 'occ', label: 'Number of Occurrences', sortable: true },
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

const { openRequests, alerts, startRequest, endRequest, handleError, axiosGet } = useRequestTracker();

const { notEmptyFields, changeTextMode, setUpOperatorWatchers, deleteActiveFilter, onDataExtend, commentSearch, onLoaded } = useSearchFields(model, schema, fields, aggregation, {
  multiple: true,
  endRequest,
  historyRequest
});

const { onData, setupCollapsibleLegends } = useSearchSession({
  urls,
  data,
  aggregation,
  emit,
  elRef,
  onDataExtend
}, 'ManuscriptSearchConfig');

const { collectionArray, collectionToggleAll, clearCollection, addManagementsToSelection, removeManagementsFromSelection, addManagementsToResults, removeManagementsFromResults } = useCollectionManagement({
  data,
  urls,
  alerts,
  startRequest,
  endRequest,
  noHistory
});

const handleDeletedActiveFilter = (field) => {
  deleteActiveFilter(field);
  onValidated(true);
};

const handleSort = ({ sortBy: newSortBy, sortAscending: newSortAscending }) => {
  sortBy.value = newSortBy;
  sortAscending.value = newSortAscending;
  currentPage.value = 1;
  loadData(true);
};

const updatePage = (newPage) => {
  currentPage.value = newPage;
  loadData(true);
};

const updatePerPage = (newPerPage) => {
  currentPerPage.value = parseInt(newPerPage);
  currentPage.value = 1;
  loadData(true);
};

const loadData = async (forcedRequest = false) => {
  if (!initialized.value) {
    if (data && data.data) {
      onData(data);
      tableData.value = data.data || [];
      totalRecords.value = data.count || 0;
      initialized.value = true;
    }
    return;
  }

  const shouldMakeRequest = actualRequest.value || forcedRequest || hasActiveFilters();
  if (!shouldMakeRequest) return;

  if (!model.value || !fields.value || !urls['manuscripts_search_api']) return;

  const filterParams = buildFilterParams(model.value)

  const params = {
    page: currentPage.value,
    limit: currentPerPage.value,
    orderBy: sortBy.value,
    ascending: sortAscending.value ? 1 : 0,
    filters: filterParams
  };

  startRequest();
  let url = urls['manuscripts_search_api'];

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
          { params, paramsSerializer: qs.stringify },
          tableCancel,
          onData,
          data
      );
      tableData.value = response?.data?.data || [];
      totalRecords.value = response?.data?.count || 0;
      if (onLoaded && aggregation.value) {
        await onLoaded(aggregation.value);
      }
    }
  } catch (error) {
    handleError(error);
    tableData.value = [];
    totalRecords.value = 0;
  } finally {
    endRequest();
  }
};

const hasActiveFilters = () => {
  if (!model.value) return false;

  for (const [key, value] of Object.entries(model.value)) {
    if (value != null && value !== '' && !(Array.isArray(value) && value.length === 0)) {
      if (key === 'comment_mode' && JSON.stringify(value) === JSON.stringify(['latin'])) continue;
      if (key === 'date_search_type' && value === 'exact') continue;
      if (key.endsWith('_op') && value === 'or') continue;

      return true;
    }
  }
  return false;
};

const del = async (row) => {
  if (!row?.id || !row?.name) return;

  submitModel.manuscript = { id: row.id, name: row.name };
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
      login: isLoginError(error)
    });
  } finally {
    endRequest();
  }
};

const submitDelete = async () => {
  if (!submitModel.manuscript?.id || !urls.manuscript_delete) return;

  startRequest();
  deleteModal.value = false;

  try {
    await axios.delete(urls.manuscript_delete.replace('manuscript_id', submitModel.manuscript.id));
    noHistory.value = true;
    await loadData(true);
    alerts.value.push({ type: 'success', message: 'Manuscript deleted successfully.' });
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while deleting the manuscript.' });
  } finally {
    endRequest();
  }
};

const modelUpdated = (fieldName) => {
  lastChangedField.value = fieldName;
};

const resetAllFilters = () => {
  if (!originalModel.value || Object.keys(originalModel.value).length === 0) {
    originalModel.value = JSON.parse(JSON.stringify(model.value));
  }
  model.value = JSON.parse(JSON.stringify(originalModel.value));
  onValidated(true);
};

const { onValidated, lastChangedField, actualRequest, initFromURL } = useFormValidation({
  model,
  fields,
  defaultOrdering,
  historyRequest,
  currentPage,
  sortBy,
  sortAscending,
  onDataRefresh: loadData
});

watch(() => model.value.comment_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'comment');
});

watch(() => schema.value?.groups, async (groups) => {
  if (!groups || !Array.isArray(groups)) return;
  await nextTick();
  const legends = elRef.value?.$el?.querySelectorAll('.vue-form-generator .collapsible legend') || [];
  if (legends.length > 0) {
    setupCollapsibleLegends(schema);
  }
}, { immediate: true });

watch(() => model.value.comment, (newValue) => {
  commentSearch.value = !!(newValue && newValue.trim().length > 0);
}, { immediate: true });

watch(() => actualRequest.value, (newVal) => {
  if (newVal && initialized.value) {
    currentPage.value = 1;
    loadData();
  }
});

if (setUpOperatorWatchers) setUpOperatorWatchers();

onMounted(async () => {
  const schemaResult = buildInitialSchema();
  schema.value.fields = schemaResult.fields;
  schema.value.groups = schemaResult.groups;

  initFromURL(aggregation.value);
  originalModel.value = JSON.parse(JSON.stringify(model.value));

  if (data && data.data) {
    tableData.value = data.data;
    totalRecords.value = data.count || 0;
    initialized.value = true;

    if (onData) {
      onData(data);
    }
  }

  if (onLoaded && aggregation.value) {
    try {
      await onLoaded(aggregation.value);
    } catch (error) {
      console.error('Error in onLoaded:', error);
    }
  }

  await nextTick();

  if (initFromURL && aggregation.value) {
    try {
      initFromURL(aggregation.value);
    } catch (error) {
      console.error('Error initializing from URL:', error);
    }
  }

  window.onpopstate = (event) => {
    if (popHistory) {
      historyRequest.value = popHistory();
      if (initialized.value) {
        loadData(true);
      }
    }
  };
});
</script>