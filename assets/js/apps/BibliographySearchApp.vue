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
          :url="urls['bibliographies_search_api']"
          :columns="tableColumns"
          :options="tableOptions"
          @data="onData"
          @loaded="onLoaded"
      >
        <template
            slot="comment"
            slot-scope="props"
        >
          <template v-if="props.row.public_comment">
            <em v-if="isEditor">Public</em>
            <ol>
              <li
                  v-for="(item, index) in props.row.public_comment"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
            </ol>
          </template>
          <template v-if="props.row.private_comment">
            <em>Private</em>
            <ol>
              <li
                  v-for="(item, index) in props.row.private_comment"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
            </ol>
          </template>
        </template>
        <template
            slot="type"
            slot-scope="props"
        >
          {{ props.row.type.name }}
        </template>
        <template
            slot="author"
            slot-scope="props"
        >
          <!-- view internal -->
          <template
              v-if="props.row.author && props.row.author.length > 0"
          >
            <ul
                v-if="props.row.author.length > 1"
            >
              <li
                  v-for="(author, index) in props.row.author"
                  :key="index"
              >
                <a
                    :href="urls['person_get'].replace('person_id', author.id)"
                    :class="{'bg-warning': !props.row.author_public || props.row.author_public.filter(auth => auth.id === author.id).length === 0}"
                >
                  {{ author.name }}
                </a>
              </li>
            </ul>
            <template v-else>
              <a
                  :href="urls['person_get'].replace('person_id', props.row.author[0].id)"
                  :class="{'bg-warning': !props.row.author_public || props.row.author_public.length === 0}"
              >
                {{ props.row.author[0].name }}
              </a>
            </template>
          </template>
          <!-- no view internal -->
          <template
              v-else-if="props.row.author_public && props.row.author_public.length > 0"
          >
            <ul
                v-if="props.row.author_public.length > 1"
            >
              <li
                  v-for="(author, index) in props.row.author_public"
                  :key="index"
              >
                <a :href="urls['person_get'].replace('person_id', author.id)">
                  {{ author.name }}
                </a>
              </li>
            </ul>
            <template v-else>
              <a :href="urls['person_get'].replace('person_id', props.row.author_public[0].id)">
                {{ props.row.author_public[0].name }}
              </a>
            </template>
          </template>
        </template>
        <a
            slot="title"
            slot-scope="props"
            :href="urls[types[props.row.type.id] + '_get'].replace(types[props.row.type.id] + '_id', props.row.id)"
            v-html="greekFont(formatTitle(props.row.title))"
        />
        <template
            slot="actions"
            slot-scope="props"
        >
          <a
              v-if="urls[types[props.row.type.id] + '_edit']"
              :href="urls[types[props.row.type.id] + '_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>
          <a
              v-else-if="urls[types[props.row.type.id] + 's_edit']"
              :href="urls[types[props.row.type.id] + 's_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
          </a>
          <a
              v-if="types[props.row.type.id] === 'book' || types[props.row.type.id] === 'journal'"
              href="#"
              class="action"
              title="Merge"
              @click.prevent="merge(props.row)"
          >
            <i class="fa fa-compress" />
          </a>
          <a
              v-if="urls[types[props.row.type.id] + '_delete']"
              href="#"
              class="action"
              title="Delete"
              @click.prevent="del(props.row)"
          >
            <i class="fa fa-trash-o" />
          </a>
          <a
              v-else-if="urls[types[props.row.type.id] + 's_edit']"
              :href="urls[types[props.row.type.id] + 's_edit'].replace(types[props.row.type.id] + '_id', props.row.id)"
              class="action"
              title="Delete"
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
import VueTables from 'vue-tables-2';
import qs from 'qs';
import { nextTick } from 'vue';

import {
  createMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import { greekFont } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";

import Alerts from "@/components/Alerts.vue";
import ActiveFilters from '../components/Search/ActiveFilters.vue';
import Merge from '../components/Edit/Modals/Merge.vue';
import CollectionManager from '../components/Search/CollectionManager.vue';

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

const tableOptions = ref({
  headings: {
    comment: 'Comment (matching lines only)',
    author: 'Author(s)',
  },
  columnsClasses: {
    author: 'no-wrap',
  },
  filterable: false,
  orderBy: {
    column: 'title',
  },
  perPage: 25,
  perPageValues: [25, 50, 100],
  sortable: ['type', 'author', 'title'],
  customFilters: ['filters'],
  rowClassCallback(row) {
    return (row.public == null || row.public) ? '' : 'warning';
  },
});

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
const resultTableRef = ref(null);
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
  defaultOrdering: ref('title'),
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
  commentSearch
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
  resultTableRef,
  alerts,
  startRequest,
  endRequest,
  noHistory
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

const tableColumns = computed(() => {
  const columns = ['type', 'author', 'title'];
  if (commentSearch.value) {
    columns.unshift('comment');
  }
  if (props.isViewInternal) {
    columns.push('actions');
    columns.push('c');
  }
  return columns;
});
const handleDeletedActiveFilter = (field) => {
  deleteActiveFilter(field);
  onValidated(true);
};

const requestFunction = async (requestData) => {
  const params = cleanParams(requestData);
  startRequest();
  let url = urls['bibliographies_search_api'];

  if (!initialized.value) {
    onData(data);
    initialized.value = true;
    endRequest();
    return {
      data: {
        data: data.data,
        count: data.count,
      },
    };
  }


  if (historyRequest.value) {
    if (historyRequest.value !== 'init') {
      url = `${url}?${historyRequest.value}`;
    }
    return await axiosGet(url, {}, tableCancel, onData, data);
  }

  if (noHistory.value===false) {
    pushHistory(params, model, originalModel, fields, tableOptions);
  } else {
    noHistory.value = false;
  }

  return await axiosGet(
      url,
      {
        params,
        paramsSerializer: qs.stringify
      },
      tableCancel,
      onData,
      data
  );};

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
      // enableField(mergeSchema.value.fields.primary);
      // enableField(mergeSchema.value.fields.secondary);
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
      // enableField(mergeSchema.value.fields.primary);
      // enableField(mergeSchema.value.fields.secondary);
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
    // update(); // Assuming this refreshes the table
    resultTableRef.value?.refresh();
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
    // Don't create a new history item
    noHistory.value = true;
    resultTableRef.value?.refresh();
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
}

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


tableOptions.value.requestFunction = requestFunction;

setUpOperatorWatchers();

onMounted(() => {
  buildSchema();
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