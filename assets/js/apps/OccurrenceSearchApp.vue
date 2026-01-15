<template>
  <div>
    <div class="col-xs-12">
      <alerts :alerts="alerts" @dismiss="alerts.splice($event, 1)" />
    </div>

    <aside class="col-sm-3">
      <div class="bg-tertiary padding-default">
        <div class="form-group">
          <a :href="urls['help']" class="action" target="_blank">
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

      <div style="position: relative; margin-bottom: 1rem;">
        <div style="text-align: center;">
          <h6 v-if="totalRecords" class="mb-0" style="display: inline-block;">
            <record-count
                :per-page="perPage"
                :total-records="totalRecords"
                :page="currentPage"
            />
          </h6>
        </div>

        <div style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
          Records: <b-select
              id="per-page"
              label="Per page"
              :selected="perPage"
              :options="[25, 50, 100].map(v => ({ value: v, text: v }))"
              @update:selected="updatePerPage"
              style="min-width: 120px; width: auto;"
          />
        </div>
      </div>

      <b-table
            :items="tableData"
            :fields="tableFields"
            :sort-by="sortBy"
            :sort-ascending="sortAscending"
            :row-class="getRowClass"
            @sort="handleSort"
        >
          <template #actionsPreRowHeader v-if="isViewInternal">
            <th>
              <input type="checkbox" @change="handleCollectionToggleAll" />
            </th>
          </template>

          <template #actionsPreRow="{ row }" v-if="isViewInternal">
            <td>
              <input
                  :id="row.id"
                  v-model="collectionArray"
                  :value="row.id"
                  type="checkbox"
              />
            </td>
          </template>

          <template #text="{ row }">
          <span class="greek">
            <template v-if="row.title">
              <ol type="A">
                <li
                    v-for="(item, index) in row.title"
                    :key="index"
                    value="20"
                    v-html="item"
                />
              </ol>
            </template>
            <template v-if="row.text">
              <ol>
                <li
                    v-for="(item, index) in row.text"
                    :key="index"
                    :value="Number(index) + 1"
                    v-html="item"
                />
              </ol>
            </template>
          </span>
          </template>

        <template #comment="{ row }">
          <template v-if="row.palaeographical_info">
            <em>Palaeographical info</em>
            <ol>
              <li
                  v-for="(item, index) in row.palaeographical_info"
                  :key="'paleo-' + index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
            </ol>
          </template>
          <template v-if="row.contextual_info">
            <em>Contextual info</em>
            <ol>
              <li
                  v-for="(item, index) in row.contextual_info"
                  :key="'context-' + index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
            </ol>
          </template>
          <template v-if="row.public_comment">
            <em v-if="isViewInternal">Public comment</em>
            <em v-else>Comment</em>
            <ol>
              <li
                  v-for="(item, index) in row.public_comment"
                  :key="'public-' + index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
            </ol>
          </template>
          <template v-if="row.private_comment">
            <em>Private comment</em>
            <ol>
              <li
                  v-for="(item, index) in row.private_comment"
                  :key="'private-' + index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
            </ol>
          </template>
        </template>

          <template #id="{ row }">
            <a :href="urls['occurrence_get'].replace('occurrence_id', row.id)">
              {{ row.id }}
            </a>
          </template>

          <template #incipit="{ row }">

            <a :href="urls['occurrence_get'].replace('occurrence_id', row.id)"
            class="greek"
            v-html="row.incipit"
            />
          </template>

          <template #manuscript="{ row }">

            <a v-if="row.manuscript"
            :href="urls['manuscript_get'].replace('manuscript_id', row.manuscript.id)"
            >
            {{ row.manuscript.name }} ({{ row.location }})
            </a>
          </template>

          <template #date="{ row }">
            <template v-if="row.date_floor_year && row.date_ceiling_year">
              <template v-if="row.date_floor_year === row.date_ceiling_year">
                {{ row.date_floor_year }}
              </template>
              <template v-else>
                {{ row.date_floor_year }} - {{ row.date_ceiling_year }}
              </template>
            </template>
          </template>

        <template #created="{ row }">
          {{ formatDate(row.created) }}
        </template>

        <template #modified="{ row }">
          {{ formatDate(row.modified) }}
        </template>


        <template #actions="{ row }" v-if="isViewInternal">

            <a :href="urls['occurrence_edit'].replace('occurrence_id', row.id)"
            class="action"
            title="Edit"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

            <a :href="urls['occurrence_edit'].replace('occurrence_id', row.id) + '?clone=1'"
            class="action"
            title="Duplicate"
            >
            <i class="fa fa-files-o" />
            </a>

            <a href="#"
            class="action"
            title="Delete"
            @click.prevent="del(row)"
            >
            <i class="fa fa-trash-o" />
            </a>
          </template>
        </b-table>
      <div
          v-if="isViewInternal"
          class="collection-select-all bottom"
          style="margin-top: 1rem; clear: both;"
      >

        <a href="#"
        @click.prevent="clearCollection()"
        >
        clear selection
        </a>
        |

        <a href="#"
        @click.prevent="collectionToggleAll()"
        >
        (un)select all on this page
        </a>
      </div>
      <div style="position: relative; margin-bottom: 5rem; margin-top: 5rem;">
        <div style="text-align: center;">
          <b-pagination
              :total-records="totalRecords"
              :per-page="perPage"
              :page="currentPage"
              @update:page="updatePage"
          />
        </div>

<!--        <div style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">-->
<!--          <button @click="downloadCSVHandler"-->
<!--                  class="btn btn-primary"-->
<!--                  :title="!isViewInternal ? 'For anonymous users, download is limited to 1000 results' : 'Download results as csv'"-->
<!--                  style="position: absolute; top: 50%; right: 1rem; transform: translateY(-50%);">-->
<!--            Download results CSV-->
<!--          </button>-->
<!--        </div>-->
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
import BTable from '@/components/Bootstrap/BTable.vue';
import BPagination from '@/components/Bootstrap/BPagination.vue';
import BSelect from '@/components/Bootstrap/BSelect.vue';
import RecordCount from '@/components/Bootstrap/RecordCount.vue';
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

const currentPage = ref(1);
const sortBy = ref('incipit');
const sortAscending = ref(true);
const tableData = ref([]);
const totalRecords = ref(0);

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

const perPage = ref(25);

const tableFields = computed(() => {
  const fields = [
    { key: 'id', label: 'ID', sortable: true, thClass: 'no-wrap' },
    { key: 'incipit', label: 'Incipit', sortable: true },
    { key: 'manuscript', label: 'Manuscript', sortable: true },
    { key: 'date', label: 'Date', sortable: true },
    { key: 'created', label: 'Created', sortable: true },

  ];

  if (textSearch.value) {
    fields.unshift({ key: 'text', label: 'Title (T.) / text (matching verses only)' });
  }
  if (commentSearch.value) {
    fields.unshift({ key: 'comment', label: 'Comment (matching lines only)' });
  }
  if (props.isViewInternal) {
    fields.push(
        { key: 'modified', label: 'Modified', sortable: true },
        { key: 'actions', label: 'Actions' }
    );
  }

  return fields;
});

const getRowClass = (row) => {
  return (row.public == null || row.public) ? '' : 'warning';
};

const fetchData = async () => {
  startRequest();

  try {
    let url = urls['occurrences_search_api'];

    if (historyRequest.value) {
      if (historyRequest.value !== 'init') {
        url = `${url}?${historyRequest.value}`;
      }
      const response = await axiosGet(url, {}, tableCancel, onData, data);
      tableData.value = response.data.data || [];
      totalRecords.value = response.data.count || 0;

      historyRequest.value = false;
      onLoaded();
      return;
    }

    const params = cleanParams({
      orderBy: sortBy.value,
      ascending: sortAscending.value ? 1 : 0,
      page: currentPage.value,
      limit: perPage.value,
      filters: constructFilterValues(model.value, fields.value)
    });

    if (params.filters?.dbbe && Array.isArray(params.filters.dbbe) && params.filters.dbbe.length === 1) {
      params.filters.dbbe = params.filters.dbbe[0];
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

    tableData.value = response.data.data || [];
    totalRecords.value = response.data.count || 0;
    onLoaded();

    if (!noHistory.value) {
      pushHistory(params, model, originalModel, fields, { value: { orderBy: { column: sortBy.value } } });
    } else {
      noHistory.value = false;
    }
  } finally {
    endRequest();
  }
};
const updatePage = (page) => {
  currentPage.value = page;
  fetchData();
};

const updatePerPage = (newPerPage) => {
  perPage.value = parseInt(newPerPage);
  currentPage.value = 1;
  fetchData();
};

const handleSort = ({ sortBy: newSortBy, sortAscending: newSortAscending }) => {
  sortBy.value = newSortBy;
  sortAscending.value = newSortAscending;
  fetchData();
};

const emit = defineEmits();

const urls = JSON.parse(props.initUrls || '{}');
const data = JSON.parse(props.initData || '{}');
const managements = JSON.parse(props.initManagements || '{}');

const formOptions = ref({
  validateAfterLoad: true,
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
});


if (props.isViewInternal) {
  model.value.text_stem = 'original';
}

const originalModel = ref({});

const schema = ref({
  fields: {},
});

const buildSchema = () => {
  const fields = {};

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

  fields.comment_mode = createLanguageToggle('comment');
  fields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    labelClasses: 'control-label',
    model: 'comment',
    validator: validatorUtil.string,
  };

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

buildSchema();

const submitModel = reactive({
  submitType: 'occurrence',
  occurrence: {},
});

const defaultOrdering = ref('incipit');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const resultTableRef = ref(null);
const aggregation = ref({});
const historyRequest = ref(null);
const occRef = ref(null);
const deleteModal = ref(false);
const delDependencies = ref({});

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


const { countRecords, updateCountRecords } = usePaginationCount(resultTableRef);

const {
  openRequests,
  alerts,
  startRequest,
  endRequest,
  cleanParams,
  handleError,
  axiosGet
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
  emitFilter: (filters) => {
    currentPage.value = 1;
    fetchData();
  },
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



const { init, onData, setupCollapsibleLegends, aggregationLoaded } = useSearchSession({
  urls,
  data,
  aggregation,
  emit,
  elRef: occRef,
  onDataExtend
}, 'OccurrenceSearchConfig');

const urlInitialized = ref(false);

watch(
    () => aggregationLoaded.value,
    (loaded) => {
      if (loaded && !urlInitialized.value) {
        initFromURL(aggregation.value);
        urlInitialized.value = true;
        nextTick(() => onValidated(true));
      }
    },
    { immediate: true }
);
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

const handleDeletedActiveFilter = (field) => {
  deleteActiveFilter(field);
  onValidated(true);
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

const handleCollectionToggleAll = () => {
  const currentData = { data: tableData.value };
  collectionToggleAll(currentData);
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
    await downloadCSV(urls, 'occurrences');
  } catch (error) {
    console.error(error);
    alerts.value.push({ type: 'error', message: 'Error downloading CSV.' });
  }
};

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

setUpOperatorWatchers();

onMounted(() => {
  originalModel.value = JSON.parse(JSON.stringify(model.value));
  historyRequest.value = popHistory();
  resultTableRef.value?.refresh();
});
</script>