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
            ref="biblioElRef"
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
              <li
                  v-for="(commentItem, index) in item.public_comment"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(commentItem)"
              />
            </ol>
          </template>
          <template v-if="item.private_comment">
            <em>Private</em>
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

        <template #type="{ item }">
          {{ item.type.name }}
        </template>

        <template #author="{ item }">
          <!-- view internal -->
          <template
              v-if="item.author && item.author.length > 0"
          >
            <ul
                v-if="item.author.length > 1"
            >
              <li
                  v-for="(author, index) in item.author"
                  :key="index"
              >
                <a
                    :href="urls['person_get'].replace('person_id', author.id)"
                    :class="{'bg-warning': !item.author_public || item.author_public.filter(auth => auth.id === author.id).length === 0}"
                >
                  {{ author.name }}
                </a>
              </li>
            </ul>
            <template v-else>
              <a
                  :href="urls['person_get'].replace('person_id', item.author[0].id)"
                  :class="{'bg-warning': !item.author_public || item.author_public.length === 0}"
              >
                {{ item.author[0].name }}
              </a>
            </template>
          </template>
          <!-- no view internal -->
          <template
              v-else-if="item.author_public && item.author_public.length > 0"
          >
            <ul
                v-if="item.author_public.length > 1"
            >
              <li
                  v-for="(author, index) in item.author_public"
                  :key="index"
              >
                <a :href="urls['person_get'].replace('person_id', author.id)">
                  {{ author.name }}
                </a>
              </li>
            </ul>
            <template v-else>
              <a :href="urls['person_get'].replace('person_id', item.author_public[0].id)">
                {{ item.author_public[0].name }}
              </a>
            </template>
          </template>
        </template>

        <template #title="{ item }">
          <a
              :href="urls[types[item.type.id] + '_get'].replace(types[item.type.id] + '_id', item.id)"
              v-html="greekFont(formatTitle(item.title))"
          />
        </template>

        <template #actions="{ item }">
          <a
              v-if="urls[types[item.type.id] + '_edit']"
              :href="urls[types[item.type.id] + '_edit'].replace(types[item.type.id] + '_id', item.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>
          <a
              v-else-if="urls[types[item.type.id] + 's_edit']"
              :href="urls[types[item.type.id] + 's_edit'].replace(types[item.type.id] + '_id', item.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>
          <a
              v-if="types[item.type.id] === 'book' || types[item.type.id] === 'journal'"
              href="#"
              class="action"
              title="Merge"
              @click.prevent="merge(item)"
          >
            <i class="fa fa-compress" />
          </a>
          <a
              v-if="urls[types[item.type.id] + '_delete']"
              href="#"
              class="action"
              title="Delete"
              @click.prevent="del(item)"
          >
            <i class="fa fa-trash-o" />
          </a>
          <a
              v-else-if="urls[types[item.type.id] + 's_edit']"
              :href="urls[types[item.type.id] + 's_edit'].replace(types[item.type.id] + '_id', item.id)"
              class="action"
              title="Delete"
          >
            <i class="fa fa-trash-o" />
          </a>
        </template>

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
    <Merge
        :show="mergeModal"
        :schema="mergeSchema"
        :merge-model="mergeModel"
        :original-merge-model="originalMergeModel"
        :alerts="mergeAlerts"
        @cancel="cancelMerge()"
        @reset="resetMerge()"
        @confirm="submitMerge()"
        @dismiss-alert="mergeAlerts.splice($event, 1)"
    >
      <table
          v-if="mergeModel.primaryFull && mergeModel.secondaryFull"
          slot="preview"
          class="table table-striped table-hover"
      >
        <thead>
        <tr>
          <th>Field</th>
          <th>Value</th>
        </tr>
        </thead>
        <tbody v-if="mergeModel.submitType === 'book'">
        <tr>
          <td>Book cluster</td>
          <td>{{ (mergeModel.primaryFull.bookCluster != null ? mergeModel.primaryFull.bookCluster.title : null) || (mergeModel.secondaryFull.bookCluster != null ? mergeModel.secondaryFull.bookCluster.title : null) }}</td>
        </tr>
        <tr>
          <td>Volume</td>
          <td>{{ mergeModel.primaryFull.volume || mergeModel.secondaryFull.volume }}</td>
        </tr>
        <tr>
          <td>Total Volumes</td>
          <td>{{ mergeModel.primaryFull.totalVolumes || mergeModel.secondaryFull.totalVolumes }}</td>
        </tr>
        <tr>
          <td>Title</td>
          <td>{{ mergeModel.primaryFull.title || mergeModel.secondaryFull.title }}</td>
        </tr>
        <tr>
          <td>Year</td>
          <td>{{ mergeModel.primaryFull.year || mergeModel.secondaryFull.year }}</td>
        </tr>
        <tr>
          <td>City</td>
          <td>{{ mergeModel.primaryFull.city || mergeModel.secondaryFull.city }}</td>
        </tr>
        <tr>
          <td>Person roles</td>
          <td>{{ formatPersonRoles(mergeModel.primaryFull.personRoles || mergeModel.secondaryFull.personRoles) }}</td>
        </tr>
        <tr>
          <td>Publisher</td>
          <td>{{ mergeModel.primaryFull.publisher || mergeModel.secondaryFull.publisher }}</td>
        </tr>
        <tr>
          <td>Book series</td>
          <td>{{ (mergeModel.primaryFull.bookSeries != null ? mergeModel.primaryFull.bookSeries.title : null) || (mergeModel.secondaryFull.bookSeries != null ? mergeModel.secondaryFull.bookSeries.title : null) }}</td>
        </tr>
        <tr>
          <td>Series volume</td>
          <td>{{ mergeModel.primaryFull.seriesVolume || mergeModel.secondaryFull.seriesVolume }}</td>
        </tr>
        <tr
            v-for="identifier in identifiers"
            :key="identifier.systemName"
        >
          <td>{{ identifier.name }}</td>
          <td>
            {{ identificationValue(identifier) }}
          </td>
        </tr>
        <tr>
          <td>Acknowledgements</td>
          <td>{{ mergeModel.primaryFull.acknowledgements || mergeModel.secondaryFull.acknowledgements }}</td>
        </tr>
        <tr>
          <td>Public comment</td>
          <td>{{ mergeModel.primaryFull.publicComment || mergeModel.secondaryFull.publicComment }}</td>
        </tr>
        <tr>
          <td>Private comment</td>
          <td>{{ mergeModel.primaryFull.privateComment || mergeModel.secondaryFull.privateComment }}</td>
        </tr>
        </tbody>
        <tbody v-else-if="mergeModel.submitType === 'journal'">
        <tr>
          <td>Title</td>
          <td>{{ mergeModel.primaryFull.name }}</td>
        </tr>
        </tbody>
      </table>
    </Merge>
    <Delete
        :show="deleteModal"
        :del-dependencies="delDependencies.value"
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
import axios from 'axios';
import qs from 'qs';
import { nextTick } from 'vue';

import BTable from "@/components/SearchTable/BTable.vue";
import BSelect from "@/components/SearchTable/BSelect.vue";
import BPagination from "@/components/SearchTable/BPagination.vue";
import {
  createMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import { greekFont } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";

import Alerts from "@/components/Alerts.vue";
import ActiveFilters from '../components/SearchFilters/ActiveFilters.vue';
import Merge from '../components/Edit/Modals/Merge.vue';
import CollectionManager from '../components/SearchFilters/CollectionManager.vue';

import { useRequestTracker } from "@/composables/searchAppComposables/useRequestTracker";
import { usePaginationCount } from "@/composables/searchAppComposables/usePaginationCount";
import { useFormValidation } from "@/composables/searchAppComposables/useFormValidation";
import { useEditMergeMigrateDelete } from "@/composables/editAppComposables/useEditMergeMigrateDelete";
import { useSearchFields } from "@/composables/searchAppComposables/useSearchFields";
import { useCollectionManagement } from "@/composables/searchAppComposables/useCollectionManagement";
import { useSearchSession } from "@/composables/searchAppComposables/useSearchSession";

import { constructFilterValues } from "@/helpers/searchAppHelpers/filterUtil";
import { popHistory, pushHistory } from "@/helpers/searchAppHelpers/historyUtil";
import { fetchDependencies } from "@/helpers/searchAppHelpers/fetchDependencies";
import validatorUtil from "@/helpers/validatorUtil";
import Delete from "@/components/Edit/Modals/Delete.vue";
import {buildFilterParams} from "@/helpers/filterParamUtil";

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

// Pagination and table state
const currentPage = ref(1);
const currentPerPage = ref(25);
const totalRecords = ref(0);
const sortBy = ref('title');
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
  validationSuccessClass: 'success',
});

const model = ref({
  title_mode: ['latin'],
  title_type: 'any',
  person: [],
  role: [],
  comment_mode: ['latin'],
});

const originalModel = ref({});

const books = ref(null);
const journals = ref(null);

const submitModel = reactive({
  submitType: null,
  article: {},
  blog_post: {},
  book: {},
  book_chapter: {},
  online_source: {},
  phd: {},
  bib_varia: {},
});

const mergeModel = reactive({
  submitType: null,
  primary: null,
  primaryFull: null,
  secondary: null,
  secondaryFull: null,
});

const defaultOrdering = ref('title');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const aggregation = ref({});
const historyRequest = ref(false);
const biblioElRef = ref(null);
const mergeModal = ref(false);
const mergeAlerts = ref([]);
const originalMergeModel = ref({});

const schema = ref({
  fields: {},
  groups: []
});

const buildSchema = () => {
  const fields = {};

  fields.type = createMultiSelect('Type');

  fields.title_mode = createLanguageToggle('title');

  fields.title = {
    type: 'input',
    inputType: 'text',
    label: 'Title',
    model: 'title',
  };

  fields.title_type = {
    type: 'checkboxes',
    styleClasses: 'field-checkboxes-labels-only field-checkboxes-lg',
    label: 'Title search options:',
    model: 'title_type',
    parentModel: 'title',
    values: [
      { value: 'all', name: 'all', toggleGroup: 'all_any_phrase' },
      { value: 'any', name: 'any', toggleGroup: 'all_any_phrase' },
      { value: 'phrase', name: 'consecutive words', toggleGroup: 'all_any_phrase' },
    ],
  };

  fields.person = createMultiSelect(
      'Person',
      {},
      {
        multiple: true,
        closeOnSelect: false,
      },
  );

  fields.role = createMultiSelect(
      'Role',
      {
        dependency: 'person',
      },
      {
        multiple: true,
        closeOnSelect: false,
      },
  );

  fields.comment_mode = createLanguageToggle('comment');

  fields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    model: 'comment',
    validator: validatorUtil.string,
  };

  const idList = [];
  for (const identifier of identifiers) {
    idList.push(createMultiSelect(
        identifier.name,
        {
          model: identifier.systemName,
        },
    ));
  }

  const groups = [];

  if (idList.length) {
    groups.push({
      styleClasses: 'collapsible collapsed',
      legend: 'External identifiers',
      fields: idList,
    });
  }

  if (props.isViewInternal) {
    fields.management = createMultiSelect(
        'Management collection',
        {
          model: 'management',
          styleClasses: 'has-warning',
        },
    );

    fields.management_inverse = {
      type: 'checkbox',
      styleClasses: 'has-warning',
      label: 'Inverse management collection selection',
      labelClasses: 'control-label',
      model: 'management_inverse',
    };
  }

  schema.value = {
    fields,
    groups,
  };
};

const types = ref({
  0: 'article',
  1: 'book',
  2: 'book_chapter',
  3: 'online_source',
  4: 'journal',
  5: 'book_cluster',
  6: 'book_series',
  7: 'blog',
  8: 'blog_post',
  9: 'phd',
  10: 'bib_varia',
});

const mergeSchema = ref({
  fields: {
    primary: createMultiSelect(
        'Primary',
        {
          required: true,
          validator: validatorUtil.required,
        },
        {
          customLabel: ({ id, name }) => `[${id}] ${name}`,
        },
    ),
    secondary: createMultiSelect(
        'Secondary',
        {
          required: true,
          validator: validatorUtil.required,
        },
        {
          customLabel: ({ id, name }) => `[${id}] ${name}`,
        },
    ),
  },
});

const fields = computed(() => {
  const res = {};
  const addField = (field) => {
    if (!field.multiple || field.multi === true) {
      res[field.model] = field;
    }
  };

  if (schema.value && schema.value.fields) {
    Object.values(schema.value.fields).forEach(addField);
  }

  return res;
});

const depUrls = computed(() => {
  const depUrls = {};
  switch (submitModel.submitType) {
    case 'article':
    case 'book':
    case 'book_chapter':
    case 'online_source':
    case 'blog_post':
    case 'phd':
    case 'bib_varia':
      depUrls.Manuscripts = {
        depUrl: urls[`manuscript_deps_by_${submitModel.submitType}`]
            .replace(`${submitModel.submitType}_id`, submitModel[submitModel.submitType].id),
        url: urls.manuscript_get,
        urlIdentifier: 'manuscript_id',
      };
      depUrls.Occurrences = {
        depUrl: urls[`occurrence_deps_by_${submitModel.submitType}`]
            .replace(`${submitModel.submitType}_id`, submitModel[submitModel.submitType].id),
        url: urls.occurrence_get,
        urlIdentifier: 'occurrence_id',
      };
      depUrls.Types = {
        depUrl: urls[`type_deps_by_${submitModel.submitType}`]
            .replace(`${submitModel.submitType}_id`, submitModel[submitModel.submitType].id),
        url: urls.type_get,
        urlIdentifier: 'type_id',
      };
      depUrls.Persons = {
        depUrl: urls[`person_deps_by_${submitModel.submitType}`]
            .replace(`${submitModel.submitType}_id`, submitModel[submitModel.submitType].id),
        url: urls.person_get,
        urlIdentifier: 'person_id',
      };
      if (submitModel.submitType === 'book') {
        depUrls['Book chapters'] = {
          depUrl: urls.book_chapter_deps_by_book.replace('book_id', submitModel.book.id),
          url: urls.book_chapter_get,
          urlIdentifier: 'book_chapter_id',
        };
      }
      break;
    case 'blog':
      depUrls['Blog posts'] = {
        depUrl: urls.blog_post_deps_by_blog.replace('blog_id', submitModel.blog.id),
        url: urls.blog_post_get,
        urlIdentifier: 'blog_post_id',
      };
      break;
    default:
      throw new Error('Unknown submit type '+submitModel.submitType);
  }
  return depUrls;
});

const countRecords = computed(() => {
  if (totalRecords.value === 0) return '';
  const start = (currentPage.value - 1) * currentPerPage.value + 1;
  const end = Math.min(currentPage.value * currentPerPage.value, totalRecords.value);
  return `Showing ${start} to ${end} of ${totalRecords.value} entries`;
});

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
  defaultOrdering,
  historyRequest,
  currentPage,
  sortBy,
  sortAscending,
  onDataRefresh: loadData
});

const {
  notEmptyFields,
  changeTextMode,
  setUpOperatorWatchers,
  onLoaded,
  deleteActiveFilter,
  onDataExtend,
  commentSearch
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
  elRef: biblioElRef,
  onDataExtend
}, 'BibliographySearchConfig');

const { delDependencies, deleteModal } = useEditMergeMigrateDelete(props.initUrls, props.initData);

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

const tableFields = computed(() => {
  const fields = [];

  if (commentSearch.value) {
    fields.push({
      key: 'comment',
      label: 'Comment (matching lines only)',
      sortable: false
    });
  }

  fields.push(
      { key: 'type', label: 'Type', sortable: true },
      { key: 'author', label: 'Author(s)', sortable: true },
      { key: 'title', label: 'Title', sortable: true }
  );

  if (props.isViewInternal) {
    fields.push(
        { key: 'actions', label: 'Actions', sortable: false },
        { key: 'c', label: '', sortable: false }
    );
  }

  return fields;
});

const identificationValue = (identifier) => {
  return (
      (mergeModel.primaryFull.identifications != null
          ? mergeModel.primaryFull.identifications[identifier.systemName]
          : null) ||
      (mergeModel.secondaryFull.identifications != null
          ? mergeModel.secondaryFull.identifications[identifier.systemName]
          : null)
  );
};

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

async function loadData(forcedRequest = false) {
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

  if (!model.value || !fields.value || !urls['bibliographies_search_api']) return;

  const filterParams = buildFilterParams(model.value)

  const params = {
    page: currentPage.value,
    limit: currentPerPage.value,
    orderBy: sortBy.value,
    ascending: sortAscending.value ? 1 : 0,
    filters: filterParams
  };

  startRequest();
  let url = urls['bibliographies_search_api'];

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
        pushHistory(params, model, originalModel, fields, defaultOrdering.value, currentPerPage.value);
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
}

const hasActiveFilters = () => {
  if (!model.value) return false;

  for (const [key, value] of Object.entries(model.value)) {
    if (value != null && value !== '' && !(Array.isArray(value) && value.length === 0)) {
      if (key === 'title_mode' && JSON.stringify(value) === JSON.stringify(['latin'])) continue;
      if (key === 'comment_mode' && JSON.stringify(value) === JSON.stringify(['latin'])) continue;
      if (key === 'title_type' && value === 'any') continue;

      return true;
    }
  }
  return false;
};

const merge = async (row) => {
  mergeModel.submitType = types.value[row.type.id];
  startRequest();

  try {
    if (types.value[row.type.id] === 'book') {
      const response = await axios.get(urls.books_get);
      books.value = response.data;
      mergeModel.primary = JSON.parse(
          JSON.stringify(
              books.value.filter((book) => book.id === row.id)[0],
          ),
      );
      mergeModel.secondary = null;
      mergeSchema.value.fields.primary.values = books.value;
      mergeSchema.value.fields.secondary.values = books.value;
      originalMergeModel.value = JSON.parse(JSON.stringify(mergeModel));
      mergeModal.value = true;
    } else if (types.value[row.type.id] === 'journal') {
      const response = await axios.get(urls.journals_get);
      journals.value = response.data;
      mergeModel.primary = JSON.parse(
          JSON.stringify(
              journals.value.filter((journal) => journal.id === row.id)[0],
          ),
      );
      mergeModel.secondary = null;
      mergeSchema.value.fields.primary.values = journals.value;
      mergeSchema.value.fields.secondary.values = journals.value;
      originalMergeModel.value = JSON.parse(JSON.stringify(mergeModel));
      mergeModal.value = true;
    }
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

const submitMerge = async () => {
  mergeModal.value = false;
  startRequest();

  try {
    let url = '';
    if (mergeModel.submitType === 'book') {
      url = urls.book_merge
          .replace('primary_id', mergeModel.primary.id)
          .replace('secondary_id', mergeModel.secondary.id);
    } else if (mergeModel.submitType === 'journal') {
      url = urls.journal_merge
          .replace('primary_id', mergeModel.primary.id)
          .replace('secondary_id', mergeModel.secondary.id);
    }

    await axios.put(url);
    await loadData(true);
    mergeAlerts.value = [];
    alerts.value.push({
      type: 'success',
      message: 'Merge successful.',
    });
  } catch (error) {
    mergeModal.value = true;
    mergeAlerts.value.push({
      type: 'error',
      message: `Something went wrong while merging the ${mergeModel.submitType}s.`,
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
        urls[`${submitModel.submitType}_delete`]
            .replace(`${submitModel.submitType}_id`, submitModel[submitModel.submitType].id),
    );
    noHistory.value = true;
    await loadData(true);
    alerts.value.push({
      type: 'success',
      message: `${submitModel.submitType.replace(/^\w/, (c) => c.toUpperCase())} deleted successfully.`,
    });
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: `Something went wrong while deleting the ${submitModel.submitType}.`,
    });
    console.error(error);
  } finally {
    endRequest();
  }
};

const del = async (row) => {
  submitModel.submitType = types.value[row.type.id];
  submitModel[submitModel.submitType] = { ...row };
  if (Array.isArray(submitModel[submitModel.submitType].title)) {
    submitModel[submitModel.submitType].name = submitModel[submitModel.submitType].original_title;
  } else {
    submitModel[submitModel.submitType].name = submitModel[submitModel.submitType].title;
  }

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

const cancelMerge = () => {
  mergeModal.value = false;
  mergeModel.primary = originalMergeModel.value.primary;
  mergeModel.secondary = originalMergeModel.value.secondary;
  mergeModel.primaryFull = originalMergeModel.value.primaryFull;
  mergeModel.secondaryFull = originalMergeModel.value.secondaryFull;
  mergeAlerts.value = [];
};

const resetMerge = () => {
  mergeModel.primary = originalMergeModel.value.primary;
  mergeModel.secondary = originalMergeModel.value.secondary;
  mergeModel.primaryFull = originalMergeModel.value.primaryFull;
  mergeModel.secondaryFull = originalMergeModel.value.secondaryFull;
};

const modelUpdated = (fieldName) => {
  lastChangedField.value = fieldName;
};

const resetAllFilters = () => {
  model.value = JSON.parse(JSON.stringify(originalModel.value));
  onValidated(true);
};

const formatTitle = (title) => {
  if (Array.isArray(title)) {
    return title[0];
  }
  return title;
};

const formatPersonRoles = (personRoles) => {
  if (personRoles == null) {
    return null;
  }
  const result = [];
  for (const key of Object.keys(personRoles)) {
    const rolePersons = [];
    for (const person of personRoles[key]) {
      rolePersons.push(person.name);
    }
    result.push(`${key.charAt(0).toUpperCase() + key.substr(1)}(s): ${rolePersons.join(', ')}`);
  }
  return result.join('<br />');
};

watch(() => model.value.comment, (newValue) => {
  if (newValue && newValue.trim().length > 0) {
    commentSearch.value = true;
  } else {
    commentSearch.value = false;
  }
}, { immediate: true });

watch(() => mergeModel.primary, async (newVal) => {
  if (newVal == null) {
    mergeModel.primaryFull = null;
  } else {
    mergeModal.value = false;
    startRequest();

    try {
      let url = '';
      if (mergeModel.submitType === 'book') {
        url = urls.book_get.replace('book_id', newVal.id);
      } else if (mergeModel.submitType === 'journal') {
        url = urls.journal_get.replace('journal_id', newVal.id);
      }

      const response = await axios.get(url);
      mergeModel.primaryFull = response.data;
      mergeModal.value = true;
    } catch (error) {
      mergeModal.value = true;
      alerts.value.push({
        type: 'error',
        message: 'Something went wrong while getting the data.',
        login: isLoginError(error),
      });
      console.error(error);
    } finally {
      endRequest();
    }
  }
});

watch(() => mergeModel.secondary, async (newVal) => {
  if (newVal == null) {
    mergeModel.secondaryFull = null;
  } else {
    mergeModal.value = false;
    startRequest();

    try {
      let url = '';
      if (mergeModel.submitType === 'book') {
        url = urls.book_get.replace('book_id', newVal.id);
      } else if (mergeModel.submitType === 'journal') {
        url = urls.journal_get.replace('journal_id', newVal.id);
      }

      const response = await axios.get(url);
      mergeModel.secondaryFull = response.data;
      mergeModal.value = true;
    } catch (error) {
      mergeModal.value = true;
      alerts.value.push({
        type: 'error',
        message: 'Something went wrong while getting the data.',
        login: isLoginError(error),
      });
      console.error(error);
    } finally {
      endRequest();
    }
  }
});

watch(() => model.value.title_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'title');
});

watch(() => model.value.comment_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'comment');
});

watch(
    () => schema.value?.groups,
    async (groups) => {
      if (!groups || !Array.isArray(groups)) return;
      await nextTick();
      const legends = biblioElRef.value?.$el?.querySelectorAll('.vue-form-generator .collapsible legend') || [];
      if (legends.length > 0) {
        setupCollapsibleLegends(schema);
      }
    },
    { immediate: true }
);

watch(() => actualRequest.value, (newVal) => {
  if (newVal && initialized.value) {
    currentPage.value = 1;
    loadData();
  }
});

if (setUpOperatorWatchers) setUpOperatorWatchers();

onMounted(async () => {
  buildSchema();
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