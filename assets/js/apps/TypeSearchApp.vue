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
        <div class="form-group">
          <a
              :href="urls['help']"
              class="action"
              target="_blank"
          >
            <i class="fa fa-info-circle" /> More information about the text search options.
          </a>
        </div>
        <vue-form-generator
            ref="typeSearchRef"
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
        <template #text="{ item }">
          <span class="greek">
            <template v-if="item.title">
              <!-- T for title: T is the 20th letter in the alphabet -->
              <ol type="A">
                <li v-for="(titleItem, index) in item.title" :key="index" value="20" v-html="titleItem" />
              </ol>
            </template>
            <template v-if="item.text">
              <ol>
                <li v-for="(textItem, index) in item.text" :key="index" :value="Number(index) + 1" v-html="textItem" />
              </ol>
            </template>
          </span>
        </template>

        <template #comment="{ item }">
          <template v-if="item.public_comment">
            <em v-if="isViewInternal">Public</em>
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

        <template #lemma="{ item }">
          <template v-if="item.lemma_text">
            <ol>
              <li v-for="(lemmaItem, index) in item.lemma_text" :key="index" :value="Number(index) + 1" v-html="greekFont(lemmaItem)" />
            </ol>
          </template>
        </template>

        <template #id="{ item }">
          <a :href="urls['type_get'].replace('type_id', item.id)">{{ item.id }}</a>
        </template>

        <template #incipit="{ item }">
          <a :href="urls['type_get'].replace('type_id', item.id)" class="greek" v-html="item.incipit" />
        </template>

        <template #numberOfOccurrences="{ item }">
          {{ item.number_of_occurrences }}
        </template>

        <template #created="{ item }">
          {{ formatDate(item.created) }}
        </template>

        <template #modified="{ item }">
          {{ formatDate(item.modified) }}
        </template>

        <template #actions="{ item }">
          <a :href="urls['type_edit'].replace('type_id', item.id)" class="action" title="Edit">
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
import { formatDate, greekFont } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";
import { downloadCSV } from "@/helpers/downloadUtil";
import { constructFilterValues } from "@/helpers/searchAppHelpers/filterUtil";
import { popHistory, pushHistory } from "@/helpers/searchAppHelpers/historyUtil";
import { fetchDependencies } from "@/helpers/searchAppHelpers/fetchDependencies";

import { useRequestTracker } from "@/composables/searchAppComposables/useRequestTracker";
import { useFormValidation } from "@/composables/searchAppComposables/useFormValidation";
import { useSearchFields } from "@/composables/searchAppComposables/useSearchFields";
import { useCollectionManagement } from "@/composables/searchAppComposables/useCollectionManagement";
import { useSearchSession } from "@/composables/searchAppComposables/useSearchSession";
import validatorUtil from "@/helpers/validatorUtil";

const props = defineProps({
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
const sortBy = ref('incipit');
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
  lemma_mode: ['greek']
});

if (props.isViewInternal) {
  model.value.text_stem = 'original';
}

const originalModel = ref({});
const schema = ref({
  fields: {},
  groups: []
});

const buildInitialSchema = () => {
  const schemaFields = {};

  schemaFields.text_mode = createLanguageToggle('text');
  schemaFields.text = {
    type: 'input',
    inputType: 'text',
    styleClasses: 'greek',
    labelClasses: 'control-label',
    label: 'Text',
    model: 'text'
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
        { value: 'stemmer', name: 'Stemmed text' }
      ]
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
      { value: 'phrase', name: 'consecutive words', toggleGroup: 'all_any_phrase' }
    ]
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
      { value: 'all', name: 'Text and title', toggleGroup: 'text_title_all' }
    ]
  };

  schemaFields.lemma_mode = createLanguageToggle('lemma');
  // disable latin
  schemaFields.lemma_mode.values[2].disabled = true;

  schemaFields.lemma = {
    type: 'input',
    inputType: 'text',
    styleClasses: 'greek',
    labelClasses: 'control-label',
    label: 'Lemma',
    model: 'lemma'
  };

  schemaFields.person = createMultiSelect('Person', {}, {
    multiple: true,
    closeOnSelect: false
  });

  schemaFields.role = createMultiSelect('Role', { dependency: 'person' }, {
    multiple: true,
    closeOnSelect: false
  });

  [schemaFields.metre_op, schemaFields.metre] = createMultiMultiSelect('Metre');
  [schemaFields.genre_op, schemaFields.genre] = createMultiMultiSelect('Genre');
  [schemaFields.subject_op, schemaFields.subject] = createMultiMultiSelect('Subject');
  [schemaFields.tag_op, schemaFields.tag] = createMultiMultiSelect('Tag');

  schemaFields.translated = createMultiSelect('Translation(s) available?', { model: 'translated' }, {
    customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No')
  });

  [schemaFields.translation_language_op, schemaFields.translation_language] = createMultiMultiSelect(
      'Translation language',
      { dependency: 'translated', model: 'translation_language' }
  );

  schemaFields.comment_mode = createLanguageToggle('comment');
  schemaFields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    labelClasses: 'control-label',
    model: 'comment',
    validator: validatorUtil.string
  };

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
        { dependency: `${identifier.systemName}_available`, model: identifier.systemName }
    ));
  }

  schemaFields.id = createMultiSelect('DBBE ID', { model: 'id' });
  schemaFields.prev_id = createMultiSelect('Former DBBE ID', { model: 'prev_id' });

  schemaFields.dbbe = createMultiSelect('Text source DBBE?', { model: 'dbbe' }, {
    customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No')
  });

  [schemaFields.acknowledgement_op, schemaFields.acknowledgement] = createMultiMultiSelect(
      'Acknowledgements',
      { model: 'acknowledgement' }
  );

  if (props.isViewInternal) {
    schemaFields.text_status = createMultiSelect('Text Status', {
      model: 'text_status',
      styleClasses: 'has-warning'
    });

    schemaFields.critical_status = createMultiSelect('Editorial Status', {
      model: 'critical_status',
      styleClasses: 'has-warning'
    });

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
  submitType: 'type',
  type: {}
});

const defaultOrdering = ref('incipit');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const historyRequest = ref(false);
const typeSearchRef = ref(null);
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
  if (!submitModel.type?.id || !urls.occurrence_deps_by_type || !urls.occurrence_get) {
    return {};
  }
  return {
    Occurrences: {
      depUrl: urls.occurrence_deps_by_type.replace('type_id', submitModel.type.id),
      url: urls.occurrence_get,
      urlIdentifier: 'occurrence_id'
    }
  };
});

const tableFields = computed(() => {
  if (!schema.value?.fields || !initialized.value) return [];

  const fields = [
    { key: 'id', label: 'ID', sortable: true, thClass: 'no-wrap' }
  ];

  if (textSearch.value) {
    fields.unshift({
      key: 'text',
      label: 'Title (T.) / text (matching verses only)',
      sortable: false
    });
  }

  if (commentSearch.value) {
    fields.unshift({
      key: 'comment',
      label: 'Comment (matching lines only)',
      sortable: false
    });
  }

  if (lemmaSearch.value) {
    fields.unshift({
      key: 'lemma',
      label: 'Lemma (matching lines in original text only)',
      sortable: false
    });
  }

  fields.push(
      { key: 'incipit', label: 'Incipit', sortable: true },
      { key: 'numberOfOccurrences', label: 'Number of Occurrences', sortable: true }
  );

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

const { openRequests, alerts, startRequest, endRequest, handleError, axiosGet } = useRequestTracker();

const { notEmptyFields, changeTextMode, setUpOperatorWatchers, deleteActiveFilter, onDataExtend, commentSearch, textSearch, lemmaSearch, onLoaded } = useSearchFields(model, schema, fields, aggregation, {
  multiple: true,
  endRequest,
  historyRequest
});

const { onData, setupCollapsibleLegends } = useSearchSession({
  urls,
  data,
  aggregation,
  emit,
  elRef: typeSearchRef,
  onDataExtend
}, 'TypeSearchConfig');

const { collectionArray, collectionToggleAll, clearCollection, addManagementsToSelection, removeManagementsFromSelection, addManagementsToResults, removeManagementsFromResults } = useCollectionManagement({
  data,
  urls,
  constructFilterValues,
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

  if (!model.value || !fields.value || !urls['types_search_api']) return;

  const filterParams = {};

  Object.entries(model.value).forEach(([key, value]) => {
    if (value != null && value !== '' && !(Array.isArray(value) && value.length === 0)) {
      if (Array.isArray(value) && value.length > 0) {
        if (key.endsWith('_mode')) {
          filterParams[key] = value[0];
        } else {
          filterParams[key] = value.map(item =>
              typeof item === 'object' && item.id ? item.id : item
          );
        }
      } else if (typeof value === 'object' && value.id) {
        filterParams[key] = value.id;
      } else {
        filterParams[key] = value;
      }
    }
  });

  const params = {
    page: currentPage.value,
    limit: currentPerPage.value,
    orderBy: sortBy.value,
    ascending: sortAscending.value ? 1 : 0,
    filters: filterParams
  };

  startRequest();
  let url = urls['types_search_api'];

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
      if (key === 'text_mode' && JSON.stringify(value) === JSON.stringify(['greek'])) continue;
      if (key === 'comment_mode' && JSON.stringify(value) === JSON.stringify(['latin'])) continue;
      if (key === 'lemma_mode' && JSON.stringify(value) === JSON.stringify(['greek'])) continue;
      if (key === 'text_fields' && value === 'text') continue;
      if (key === 'text_combination' && value === 'all') continue;
      if (key === 'text_stem' && value === 'original') continue;
      if (key.endsWith('_op') && value === 'or') continue;

      return true;
    }
  }
  return false;
};

const del = async (row) => {
  if (!row?.id || !row?.incipit) return;

  submitModel.type = { id: row.id, name: row.incipit };
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
  if (!submitModel.type?.id || !urls.type_delete) return;

  startRequest();
  deleteModal.value = false;

  try {
    await axios.delete(urls.type_delete.replace('type_id', submitModel.type.id));
    noHistory.value = true;
    await loadData(true);
    alerts.value.push({ type: 'success', message: 'Type deleted successfully.' });
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while deleting the type.' });
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

watch(() => model.value.text_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'text');
});

watch(() => model.value.comment_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'comment');
});

watch(() => model.value.lemma_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'lemma');
});

watch(() => schema.value?.groups, async (groups) => {
  if (!groups || !Array.isArray(groups)) return;
  await nextTick();
  const legends = typeSearchRef.value?.$el?.querySelectorAll('.vue-form-generator .collapsible legend') || [];
  if (legends.length > 0) {
    setupCollapsibleLegends(schema);
  }
}, { immediate: true });

watch(() => model.value.text, (newValue) => {
  textSearch.value = !!(newValue && newValue.trim().length > 0);
}, { immediate: true });

watch(() => model.value.comment, (newValue) => {
  commentSearch.value = !!(newValue && newValue.trim().length > 0);
}, { immediate: true });

watch(() => model.value.lemma, (newValue) => {
  lemmaSearch.value = !!(newValue && newValue.trim().length > 0);
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
});</script>
<style scoped>
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

.per-page-container {
  display: flex;
  justify-content: right;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.per-page-container label {
  margin: 0;
}

.per-page-container select {
  width: auto;
}

.collection-controls a {
  color: #007bff;
  text-decoration: none;
  margin: 0 0.25rem;
}
</style>