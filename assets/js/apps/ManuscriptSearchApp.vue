<template>
  <div>
    <div class="col-xs-12">
      <alerts :alerts="alerts" @dismiss="alerts.splice($event, 1)" />
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

        <template #comment="{ row }">
          <template v-if="row.public_comment">
            <em v-if="isEditor">Public</em>
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
            <em>Private</em>
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

        <template #name="{ row }">
          <a :href="urls['manuscript_get'].replace('manuscript_id', row.id)">
            {{ row.name }}
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

        <template #content="{ row }">
          <template v-if="row.content">
            <template v-for="(displayContent, index) in [row.content.filter((content) => content['display'])]">
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
                {{ displayContent[0]?.name }}
              </template>
            </template>
          </template>
        </template>

        <template #occ="{ row }">
          {{ row.number_of_occurrences }}
        </template>

        <template #created="{ row }">
          {{ formatDate(row.created) }}
        </template>

        <template #modified="{ row }">
          {{ formatDate(row.modified) }}
        </template>

        <template #actions="{ row }" v-if="isViewInternal">
          <a
              :href="urls['manuscript_edit'].replace('manuscript_id', row.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>

          <a
              href="#"
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
        <a href="#" @click.prevent="clearCollection()">
          clear selection
        </a>
        |
        <a href="#" @click.prevent="collectionToggleAll()">
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

import {
  createLanguageToggle,
  createMultiMultiSelect,
  createMultiSelect,
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
import { useEditMergeMigrateDelete } from "@/composables/editAppComposables/useEditMergeMigrateDelete";
import { useSearchFields } from "@/composables/searchAppComposables/useSearchFields";
import { useCollectionManagement } from "@/composables/searchAppComposables/useCollectionManagement";
import { useSearchSession } from "@/composables/searchAppComposables/useSearchSession";
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
  initIdentifiers: {
    type: String,
    default: '',
  },
  initManagements: {
    type: String,
    default: '',
  },
});

const emit = defineEmits();

const urls = JSON.parse(props.initUrls);
const data = JSON.parse(props.initData);
const identifiers = JSON.parse(props.initIdentifiers);
const managements = JSON.parse(props.initManagements);

const currentPage = ref(1);
const sortBy = ref('name');
const sortAscending = ref(true);
const tableData = ref([]);
const totalRecords = ref(0);
const perPage = ref(25);

const formOptions = ref({
  validateAfterLoad: true,
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
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
  acknowledgement_op: 'or',
  exactly_dated: false,
});

const originalModel = ref({});

const schema = ref({
  fields: {},
  groups: [],
});

const buildSchema = () => {
  const fields = {};

  fields.city = createMultiSelect('City');
  fields.library = createMultiSelect('Library', { dependency: 'city' });
  fields.collection = createMultiSelect('Collection', { dependency: 'library' });
  fields.shelf = createMultiSelect('Shelf number', { model: 'shelf', dependency: 'collection' });

  fields.year_from = {
    type: 'input',
    inputType: 'number',
    label: 'Year from',
    model: 'year_from',
    min: YEAR_MIN,
    max: YEAR_MAX,
    validator: validatorUtil.number,
  };

  fields.year_to = {
    type: 'input',
    inputType: 'number',
    label: 'Year to',
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

  fields.exactly_dated = {
    type: 'checkbox',
    label: 'Exactly dated:',
    labelClasses: 'control-label',
    styleClasses: 'has-warning',
    model: 'exactly_dated',
    default: false,
  };

  [fields.content_op, fields.content] = createMultiMultiSelect('Content');

  fields.person = createMultiSelect('Person', {}, {
    multiple: true,
    closeOnSelect: false,
  });

  fields.role = createMultiSelect('Role', { dependency: 'person' }, {
    multiple: true,
    closeOnSelect: false,
  });

  [fields.origin_op, fields.origin] = createMultiMultiSelect('Origin');

  fields.comment_mode = createLanguageToggle('comment');

  fields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    model: 'comment',
    validator: validatorUtil.string,
  };

  [fields.acknowledgement_op, fields.acknowledgement] = createMultiMultiSelect('Acknowledgements', {
    model: 'acknowledgement',
  });

  if (props.isViewInternal) {
    fields.public = createMultiSelect('Public', {
      styleClasses: 'has-warning',
    }, {
      customLabel: ({ name }) => name === 'true' ? 'Public only' : 'Internal only',
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

  const idList = [];
  for (const identifier of identifiers) {
    idList.push(createMultiSelect(
        `${identifier.name} available?`,
        { model: `${identifier.systemName}_available` },
        {
          customLabel: ({ name }) => name === 'true' ? 'Yes' : 'No',
        }
    ));
    idList.push(createMultiSelect(
        identifier.name,
        {
          dependency: `${identifier.systemName}_available`,
          model: identifier.systemName,
        },
        {
          optionsLimit: 7000,
        }
    ));
  }

  schema.value.groups = [{
    styleClasses: 'collapsible collapsed',
    legend: 'External identifiers',
    fields: idList,
  }];
};

buildSchema();

const submitModel = reactive({
  submitType: 'manuscript',
  manuscript: {},
});

const deleteModal = ref(false);
const delDependencies = ref({});

const defaultOrdering = ref('name');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const resultTableRef = ref(null);
const aggregation = ref({});
const historyRequest = ref(null);
const elRef = ref(null);

const tableFields = computed(() => {
  const fields = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'date', label: 'Date', sortable: true },
    { key: 'content', label: 'Content' },
    { key: 'created', label: 'Created', sortable: true },
  ];

  if (commentSearch.value) {
    fields.unshift({ key: 'comment', label: 'Comment (matching lines only)' });
  }

  if (props.isViewInternal) {
    fields.push(
        { key: 'modified', label: 'Modified', sortable: true },
        { key: 'occ', label: 'Occurrences', sortable: true },
        { key: 'actions', label: 'Actions' }
    );

    const occIndex = fields.findIndex(f => f.key === 'occ');
    const createdIndex = fields.findIndex(f => f.key === 'created');
    if (occIndex !== -1 && createdIndex !== -1) {
      const [occField] = fields.splice(occIndex, 1);
      fields.splice(createdIndex, 0, occField);
    }
  }

  return fields;
});

const getRowClass = (row) => {
  return (row.public == null || row.public) ? '' : 'warning';
};

// ── Fields computed ──────────────────────────────────────────────────────────
const fields = computed(() => {
  const res = {};
  if (schema.value) {
    if (schema.value.fields) {
      Object.values(schema.value.fields).forEach(field => {
        if (!field.multiple || field.multi === true) {
          res[field.model] = field;
        }
      });
    }
    if (schema.value.groups) {
      schema.value.groups.forEach(group => {
        if (group.fields) {
          group.fields.forEach(field => {
            res[field.model] = field;
          });
        }
      });
    }
  }
  return res;
});

const depUrls = computed(() => ({
  Occurrences: {
    depUrl: urls.occurrence_deps_by_manuscript.replace(
        'manuscript_id',
        submitModel.manuscript.id
    ),
    url: urls.occurrence_get,
    urlIdentifier: 'occurrence_id',
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
  axiosGet,
} = useRequestTracker();

const {
  onValidated,
  lastChangedField,
  actualRequest,
  initFromURL,
} = useFormValidation({
  model,
  fields,
  resultTableRef,
  defaultOrdering,
  emitFilter: () => {
    currentPage.value = 1;
    fetchData();
  },
  historyRequest,
});

const {
  notEmptyFields,
  changeTextMode,
  setUpOperatorWatchers,
  onLoaded,
  deleteActiveFilter,
  onDataExtend,
  commentSearch,
} = useSearchFields(model, schema, fields, aggregation, {
  multiple: true,
  updateCountRecords,
  initFromURL,
  endRequest,
  historyRequest,
});

const { init, onData, setupCollapsibleLegends, aggregationLoaded } = useSearchSession({
  urls,
  data,
  aggregation,
  emit,
  elRef,
  onDataExtend,
}, 'ManuscriptSearchConfig');

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
  noHistory,
});

const fetchData = async () => {
  startRequest();

  try {
    let url = urls['manuscripts_search_api'];

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
      filters: constructFilterValues(model.value, fields.value),
    });

    const response = await axiosGet(
        url,
        {
          params,
          paramsSerializer: qs.stringify,
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

const handleCollectionToggleAll = () => {
  const currentData = { data: tableData.value };
  collectionToggleAll(currentData);
};

const del = async (row) => {
  submitModel.manuscript = row;
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
        urls.manuscript_delete.replace('manuscript_id', submitModel.manuscript.id)
    );
    noHistory.value = true;
    fetchData();
    alerts.value.push({ type: 'success', message: 'Manuscript deleted successfully.' });
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while deleting the manuscript.' });
    console.error(error);
  } finally {
    endRequest();
  }
};

const modelUpdated = (fieldName) => {
  lastChangedField.value = fieldName;
};

const handleDeletedActiveFilter = (field) => {
  deleteActiveFilter(field);
  onValidated(true);
};

const resetAllFilters = () => {
  model.value = JSON.parse(JSON.stringify(originalModel.value));
  noHistory.value = true;
  window.history.replaceState(
      {},
      document.title,
      document.location.pathname
  );
  onValidated(true);
};

const downloadCSVHandler = async () => {
  try {
    await downloadCSV(urls, 'manuscripts');
  } catch (error) {
    console.error(error);
    alerts.value.push({ type: 'error', message: 'Error downloading CSV.' });
  }
};

watch(() => model.value.management, (newVal) => {
  if (!newVal || (Array.isArray(newVal) && newVal.length === 0)) {
    model.value.management_inverse = false;
  }
});

watch(() => model.value.comment_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'comment');
});

watch(elRef, (el) => {
  if (el) setupCollapsibleLegends(schema);
});

watch(() => model.value.comment, (newValue) => {
  commentSearch.value = !!(newValue && newValue.trim().length > 0);
}, { immediate: true });

setUpOperatorWatchers();

onMounted(() => {
  originalModel.value = JSON.parse(JSON.stringify(model.value));
  historyRequest.value = popHistory();
  resultTableRef.value?.refresh();
});
</script>