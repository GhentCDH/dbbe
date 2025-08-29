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
              :href="urls['help'] + '#how-to-search-for-persons'"
              class="action"
              target="_blank"
          >
            <i class="fa fa-info-circle" />
            More information about the person search options.
          </a>
        </div>
        <vue-form-generator
            ref="personElRef"
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

        <template #name="{ item }">
          <a
              v-if="item.name.constructor !== Array"
              :href="urls['person_get'].replace('person_id', item.id)"
          >
            {{ item.name }}
          </a>
          <template v-else>
            <a
                v-if="item.name.length === 1"
                :href="urls['person_get'].replace('person_id', item.id)"
                v-html="item.name[0]"
            />
            <ul v-else>
              <li
                  v-for="(nameItem, index) in item.name"
                  :key="index"
                  v-html="nameItem"
              />
            </ul>
          </template>
        </template>

        <template #identification="{ item }">
          <template v-if="hasIdentification(item)">
            {{ formatIdentification(item) }}
          </template>
        </template>

        <template #self_designation="{ item }">
          <template v-if="item.self_designation">
            <ul v-if="item.self_designation.length > 1">
              <li
                  v-for="(self_designation, index) in item.self_designation"
                  :key="index"
              >
                {{ self_designation.name }}
              </li>
            </ul>
            <template v-else>
              <span>{{ item.self_designation[0].name }}</span>
            </template>
          </template>
        </template>

        <template #office="{ item }">
          <template v-if="item.office">
            <template v-for="(displayOffice, index) in [item.office.filter((office) => office['display'])]">
              <ul
                  v-if="displayOffice.length > 1"
                  :key="index"
              >
                <li
                    v-for="(office, officeIndex) in displayOffice"
                    :key="officeIndex"
                >
                  {{ office.name }}
                </li>
              </ul>
              <template v-else>
                {{ displayOffice[0]?.name }}
              </template>
            </template>
          </template>
        </template>

        <template #date="{ item }">
          <template v-if="item.born_date_floor_year || item.born_date_ceiling_year || item.death_date_floor_year || item.death_date_ceiling_year">
            {{ formatInterval(item.born_date_floor_year, item.born_date_ceiling_year, item.death_date_floor_year, item.death_date_ceiling_year) }}
          </template>
        </template>

        <template #deathdate="{ item }">
          <template v-if="item.death_date_floor_year && item.death_date_ceiling_year">
            <template v-if="item.death_date_floor_year === item.death_date_ceiling_year">
              {{ item.death_date_floor_year }}
            </template>
            <template v-else>
              {{ item.death_date_floor_year }} - {{ item.death_date_ceiling_year }}
            </template>
          </template>
        </template>

        <template #created="{ item }">
          {{ formatDate(item.created) }}
        </template>

        <template #modified="{ item }">
          {{ formatDate(item.modified) }}
        </template>

        <template #actions="{ item }">
          <a
              :href="urls['person_edit'].replace('person_id', item.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>
          <a
              href="#"
              class="action"
              title="Merge"
              @click.prevent="merge(item)"
          >
            <i class="fa fa-compress" />
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
      <Alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
    </div>
    <Merge
        :show="mergeModal"
        :schema="mergePersonSchema"
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
        <tbody>
        <tr>
          <td>First Name</td>
          <td>{{ mergeModel.primaryFull.firstName }}</td>
        </tr>
        <tr>
          <td>Last Name</td>
          <td>{{ mergeModel.primaryFull.lastName }}</td>
        </tr>
        <tr>
          <td>Extra</td>
          <td>{{ mergeModel.primaryFull.extra }}</td>
        </tr>
        <tr>
          <td>Unprocessed</td>
          <td>{{ (mergeModel.primaryFull.firstName || mergeModel.primaryFull.lastName || mergeModel.primary.extra) ? '' : mergeModel.primary.unprocessed }}</td>
        </tr>
        <tr>
          <td>Historical</td>
          <td>{{ mergeModel.primaryFull.historical ? 'Yes' : 'No' }}</td>
        </tr>
        <tr>
          <td>Modern</td>
          <td>{{ mergeModel.primaryFull.modern ? 'Yes' : 'No' }}</td>
        </tr>
        <tr>
          <td>Born Date</td>
          <td>{{ formatMergeDate(mergeModel.primaryFull.dates, 'born') }}</td>
        </tr>
        <tr>
          <td>Death Date</td>
          <td>{{ formatMergeDate(mergeModel.primaryFull.dates, 'died') }}</td>
        </tr>
        <tr
            v-for="identifier in identifiers"
            :key="identifier.systemName"
        >
          <td>{{ identifier.name }}</td>
          <td>{{ getMergedIdentification(identifier) }}</td>
        </tr>
        <tr>
          <td>(Self) designation</td>
          <td>{{ formatObjectArray([
            ...(mergeModel.primaryFull.selfDesignations || []),
            ...(mergeModel.secondaryFull.selfDesignations || [])
          ]) }}</td>
        </tr>
        <tr>
          <td>Offices</td>
          <td>{{ formatObjectArray([
            ...(mergeModel.primaryFull.officesWithParents || []),
            ...(mergeModel.secondaryFull.officesWithParents || [])
          ]) }}</td>
        </tr>
        <tr>
          <td>Public comment</td>
          <td>{{ mergeModel.primaryFull.publicComment }}</td>
        </tr>
        <tr>
          <td>Private comment</td>
          <td>{{ mergeModel.primaryFull.privateComment }}</td>
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
import Delete from '../components/Edit/Modals/Delete.vue';
import Merge from "../components/Edit/Modals/Merge.vue";
import Alerts from "@/components/Alerts.vue";
import qs from 'qs';
import { nextTick } from 'vue';

import BTable from "@/components/SearchTable/BTable.vue";
import BSelect from "@/components/SearchTable/BSelect.vue";
import BPagination from "@/components/SearchTable/BPagination.vue";
import ActiveFilters from '../components/SearchFilters/ActiveFilters.vue';
import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle,
  removeGreekAccents,
  enableField
} from '@/helpers/formFieldUtils';
import { formatDate, greekFont, YEAR_MAX, YEAR_MIN, changeMode } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";

import { useRequestTracker } from "@/composables/searchAppComposables/useRequestTracker";
import { useFormValidation } from "@/composables/searchAppComposables/useFormValidation";
import { useSearchFields } from "@/composables/searchAppComposables/useSearchFields";
import { useCollectionManagement } from "@/composables/searchAppComposables/useCollectionManagement";
import { useEditMergeMigrateDelete } from "@/composables/editAppComposables/useEditMergeMigrateDelete";
import CollectionManager from '../components/SearchFilters/CollectionManager.vue';
import { popHistory, pushHistory } from "@/helpers/searchAppHelpers/historyUtil";
import { fetchDependencies } from "@/helpers/searchAppHelpers/fetchDependencies";
import { downloadCSV } from "@/helpers/downloadUtil";
import { useSearchSession } from "@/composables/searchAppComposables/useSearchSession";
import validatorUtil from '@/helpers/validatorUtil';
import {buildFilterParams} from "@/helpers/searchAppHelpers/filterUtil";

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
  initPersons: {
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
  validationSuccessClass: 'success',
});

const model = ref({
  date_search_type: 'exact',
  role: [],
  role_op: 'or',
  office: [],
  office_op: 'or',
  self_designation: [],
  self_designation_mode: ['greek'],
  self_designation_op: 'or',
  origin: [],
  origin_op: 'or',
  comment_mode: ['latin'],
  acknowledgement: [],
  acknowledgement_op: 'or',
});

const originalModel = ref({});
const persons = ref(null);

const submitModel = reactive({
  submitType: 'person',
  person: {},
});

const mergeModel = reactive({
  submitType: 'persons',
  primary: null,
  primaryFull: null,
  secondary: null,
  secondaryFull: null,
});

const defaultOrdering = ref('name');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const aggregation = ref({});
const historyRequest = ref(false);
const personElRef = ref(null);
const mergeModal = ref(false);
const mergeAlerts = ref([]);
const originalMergeModel = ref({});

// Build schema
const schema = ref({
  fields: {},
  groups: [],
});

const mergePersonSchema = ref({
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

const buildSchema = () => {
  const fields = {};

  fields.name = {
    type: 'input',
    inputType: 'text',
    label: 'Name',
    model: 'name',
  };

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

  [fields.role_op, fields.role] = createMultiMultiSelect('Role');
  [fields.office_op, fields.office] = createMultiMultiSelect('Office');

  fields.self_designation_mode = createLanguageToggle(
      'self_designation',
      {
        styleClasses: 'field-inline-options field-checkboxes-labels-only field-checkboxes-sm two-line',
      },
  );
  // disable latin
  fields.self_designation_mode.values[2].disabled = true;

  [fields.self_designation_op, fields.self_designation] = createMultiMultiSelect(
      '(Self) designation',
      {
        styleClasses: 'greek',
        model: 'self_designation',
      },
      {
        internalSearch: false,
        onSearch: greekBetaSearch,
      },
  );

  [fields.origin_op, fields.origin] = createMultiMultiSelect(
      'Provenance',
      {
        model: 'origin',
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

  [fields.acknowledgement_op, fields.acknowledgement] = createMultiMultiSelect(
      'Acknowledgements',
      {
        model: 'acknowledgement',
      },
  );

  const idList = [];
  for (const identifier of identifiers) {
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

  const groups = [
    {
      styleClasses: 'collapsible collapsed',
      legend: 'External identifiers',
      fields: idList,
    },
  ];

  if (props.isViewInternal) {
    fields.historical = createMultiSelect(
        'Historical',
        {
          styleClasses: 'has-warning',
        },
        {
          customLabel: ({ _id, name }) => (name === 'true' ? 'Historical only' : 'Non-historical only'),
        },
    );
    fields.modern = createMultiSelect(
        'Modern',
        {
          styleClasses: 'has-warning',
        },
        {
          customLabel: ({ _id, name }) => (name === 'true' ? 'Modern only' : 'Non-modern only'),
        },
    );
    fields.public = createMultiSelect(
        'Public',
        {
          styleClasses: 'has-warning',
        },
        {
          customLabel: ({ _id, name }) => (name === 'true' ? 'Public only' : 'Internal only'),
        },
    );
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

const fields = computed(() => {
  const res = {};
  const addField = (field) => {
    if (!field.multiple || field.multi === true) {
      res[field.model] = field;
    }
  };

  if (schema.value) {
    if (schema.value.fields) {
      Object.values(schema.value.fields).forEach(addField);
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
  Manuscripts: {
    depUrl: urls.manuscript_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.manuscript_get,
    urlIdentifier: 'manuscript_id',
  },
  Occurrences: {
    depUrl: urls.occurrence_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.occurrence_get,
    urlIdentifier: 'occurrence_id',
  },
  Types: {
    depUrl: urls.type_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.type_get,
    urlIdentifier: 'type_id',
  },
  Articles: {
    depUrl: urls.article_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.article_get,
    urlIdentifier: 'article_id',
  },
  Books: {
    depUrl: urls.book_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.book_get,
    urlIdentifier: 'book_id',
  },
  'Book chapters': {
    depUrl: urls.book_chapter_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.book_chapter_get,
    urlIdentifier: 'book_chapter_id',
  },
  Contents: {
    depUrl: urls.content_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.contents_edit,
    urlIdentifier: 'content_id',
  },
  'Blog posts': {
    depUrl: urls.blog_post_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.blog_post_get,
    urlIdentifier: 'blog_post_id',
  },
  'PhD theses': {
    depUrl: urls.phd_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.phd_get,
    urlIdentifier: 'phd_id',
  },
  'Bib varia': {
    depUrl: urls.bib_varia_deps_by_person.replace('person_id', submitModel.person.id),
    url: urls.bib_varia_get,
    urlIdentifier: 'bib_varia_id',
  },
}));

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
  elRef: personElRef,
  onDataExtend
}, 'PersonSearchConfig');

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
      { key: 'name', label: 'Name', sortable: true },
      { key: 'identification', label: 'Identification', sortable: false },
      { key: 'self_designation', label: '(Self) designation', sortable: false },
      { key: 'office', label: 'Office', sortable: false },
      { key: 'date', label: 'Date', sortable: true }
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

  if (!model.value || !fields.value || !urls['persons_search_api']) return;

  const filterParams = buildFilterParams(model.value)

  const params = {
    page: currentPage.value,
    limit: currentPerPage.value,
    orderBy: sortBy.value,
    ascending: sortAscending.value ? 1 : 0,
    filters: filterParams
  };

  startRequest();
  let url = urls['persons_search_api'];

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
      if (key === 'self_designation_mode' && JSON.stringify(value) === JSON.stringify(['greek'])) continue;
      if (key === 'comment_mode' && JSON.stringify(value) === JSON.stringify(['latin'])) continue;
      if (key === 'date_search_type' && value === 'exact') continue;
      if (key.endsWith('_op') && value === 'or') continue;

      return true;
    }
  }
  return false;
};

const getMergedIdentification = (identifier) => {
  const { systemName } = identifier;
  const primary = mergeModel.primaryFull?.identifications?.[systemName] || [];
  const secondary = mergeModel.secondaryFull?.identifications?.[systemName] || [];
  return [...primary, ...secondary];
};

const merge = async (row) => {
  startRequest();
  try {
    const response = await axios.get(urls.persons_get);
    persons.value = response.data;
    mergeModel.primary = JSON.parse(
        JSON.stringify(
            persons.value.filter((person) => person.id === row.id)[0],
        ),
    );
    mergeModel.secondary = null;
    mergePersonSchema.value.fields.primary.values = persons.value;
    mergePersonSchema.value.fields.secondary.values = persons.value;
    enableField(mergePersonSchema.value.fields.primary);
    enableField(mergePersonSchema.value.fields.secondary);
    originalMergeModel.value = JSON.parse(JSON.stringify(mergeModel));
    mergeModal.value = true;
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while getting the person data.',
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
    await axios.put(
        urls.person_merge
            .replace('primary_id', mergeModel.primary.id)
            .replace('secondary_id', mergeModel.secondary.id),
    );
    mergeAlerts.value = [];
    alerts.value.push({ type: 'success', message: 'Merge successful.' });
    await loadData(true);
  } catch (error) {
    mergeModal.value = true;
    mergeAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while merging the persons.',
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
    await axios.delete(urls.person_delete.replace('person_id', submitModel.person.id));
    noHistory.value = true;
    await loadData(true);
    alerts.value.push({ type: 'success', message: 'Person deleted successfully.' });
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while deleting the person.' });
    console.error(error);
  } finally {
    endRequest();
  }
};

const del = async (row) => {
  submitModel.person = {
    id: row.id,
    name: row.original_name == null ? row.name : row.original_name,
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

const cancelMerge = () => {
  mergeModal.value = false;
  mergeModel.primary = null;
  mergeModel.secondary = null;
  mergeModel.primaryFull = null;
  mergeModel.secondaryFull = null;
};

const resetMerge = () => {
  Object.assign(mergeModel, originalMergeModel.value);
};

const modelUpdated = (fieldName) => {
  lastChangedField.value = fieldName;
};

const resetAllFilters = () => {
  model.value = JSON.parse(JSON.stringify(originalModel.value));
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

const formatMergeDate = (mergedDate, type) => {
  if (mergedDate?.filter((d) => d.type === type).length === 1) {
    return formatPersonDate(mergedDate.filter((d) => d.type === type)[0].date);
  }
  return null;
};

const formatPersonDate = (date) => {
  if (date == null || date.floor == null || date.ceiling == null) {
    return null;
  }
  return `${date.floor} - ${date.ceiling}`;
};

const formatInterval = (bornFloor, bornCeiling, deathFloor, deathCeiling) => {
  const born = bornFloor === bornCeiling ? bornFloor : `${bornFloor}-${bornCeiling}`;
  const death = deathFloor === deathCeiling ? deathFloor : `${deathFloor}-${deathCeiling}`;
  return born === death ? born : `(${born}) - (${death})`;
};

const formatObjectArray = (objects) => {
  if (objects == null || objects.length === 0) {
    return null;
  }
  return objects.map((object) => object.name).join(', ');
};

const hasIdentification = (person) => {
  for (const identifier of identifiers) {
    if (person[identifier.systemName] != null && person[identifier.systemName].length > 0) {
      return true;
    }
  }
  return false;
};

const formatIdentification = (person) => {
  const result = [];
  for (const identifier of identifiers) {
    if (person[identifier.systemName] != null && person[identifier.systemName].length > 0) {
      result.push(`${identifier.name}: ${person[identifier.systemName].join(', ')}`);
    }
  }
  return result.join(' - ');
};

const greekBetaSearch = (searchQuery) => {
  if (model.value.self_designation_mode[0] === 'greek') {
    schema.value.fields.self_designation.values = schema.value.fields.self_designation.originalValues.filter(
        (option) => removeGreekAccents(option.name).includes(removeGreekAccents(searchQuery)),
    );
    return;
  }
  if (model.value.self_designation_mode[0] === 'betacode') {
    schema.value.fields.self_designation.values = schema.value.fields.self_designation.originalValues.filter(
        (option) => removeGreekAccents(option.name).includes(
            changeMode('betacode', 'greek', searchQuery),
        ),
    );
    return;
  }
  if (model.value.self_designation_mode[0] === 'latin') {
    schema.value.fields.self_designation.values = schema.value.fields.self_designation.originalValues.filter(
        (option) => option.name.includes(searchQuery),
    );
  }
};

watch(() => model.value.comment, (newValue) => {
  if (newValue && newValue.trim().length > 0) {
    commentSearch.value = true;
  } else {
    commentSearch.value = false;
  }
}, { immediate: true });

watch(() => mergeModel.primary, async (newPrimary) => {
  if (newPrimary == null) {
    mergeModel.primaryFull = null;
  } else {
    mergeModal.value = false;
    startRequest();
    try {
      const response = await axios.get(urls.person_get.replace('person_id', newPrimary.id));
      mergeModel.primaryFull = response.data;
      mergeModal.value = true;
    } catch (error) {
      mergeModal.value = true;
      alerts.value.push({
        type: 'error',
        message: 'Something went wrong while getting the person data.',
        login: isLoginError(error),
      });
      console.error(error);
    } finally {
      endRequest();
    }
  }
});

watch(() => mergeModel.secondary, async (newSecondary) => {
  if (newSecondary == null) {
    mergeModel.secondaryFull = null;
  } else {
    mergeModal.value = false;
    startRequest();
    try {
      const response = await axios.get(urls.person_get.replace('person_id', newSecondary.id));
      mergeModel.secondaryFull = response.data;
      mergeModal.value = true;
    } catch (error) {
      mergeModal.value = true;
      alerts.value.push({
        type: 'error',
        message: 'Something went wrong while getting the person data.',
        login: isLoginError(error),
      });
      console.error(error);
    } finally {
      endRequest();
    }
  }
});

watch(() => model.value.comment_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'comment');
});

watch(
    () => schema.value?.groups,
    async (groups) => {
      if (!groups || !Array.isArray(groups)) return;
      await nextTick();
      const legends = personElRef.value?.$el?.querySelectorAll('.vue-form-generator .collapsible legend') || [];
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