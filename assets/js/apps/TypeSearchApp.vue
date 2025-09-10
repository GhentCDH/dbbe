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
            <i class="fa fa-info-circle" /> More information about the text search options.;
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
          :url="urls['types_search_api']"
          :columns="tableColumns"
          :options="tableOptions"
          @data="onData"
          @loaded="onLoaded"
      >
        <span
            slot="text"
            slot-scope="props"
        >
          <template v-if="props.row.title">
            <!-- T for title: T is the 20th letter in the alphabet -->
            <ol
                type="A"
                class="greek"
            >
              <li
                  v-for="(item, index) in props.row.title"
                  :key="index"
                  value="20"
                  v-html="item"
              />
            </ol>
          </template>
          <template v-if="props.row.text">
            <ol class="greek">
              <li
                  v-for="(item, index) in props.row.text"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="item"
              />
            </ol>
          </template>
        </span>
        <template
            slot="comment"
            slot-scope="props"
        >
          <template v-if="props.row.public_comment">
            <em v-if="isViewInternal">Public</em>
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
            slot="lemma"
            slot-scope="props"
        >
          <template v-if="props.row.lemma_text">
            <ol>
              <li
                  v-for="(item, index) in props.row.lemma_text"
                  :key="index"
                  :value="Number(index) + 1"
                  v-html="greekFont(item)"
              />
            </ol>
          </template>
        </template>
        <a
            slot="id"
            slot-scope="props"
            :href="urls['type_get'].replace('type_id', props.row.id)"
        >
          {{ props.row.id }}
        </a>
        <a
            slot="incipit"
            slot-scope="props"
            :href="urls['type_get'].replace('type_id', props.row.id)"
            class="greek"
            v-html="props.row.incipit"
        />
        <template
            slot="numberOfOccurrences"
            slot-scope="props"
        >
          {{ props.row.number_of_occurrences }}
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
              :href="urls['type_edit'].replace('type_id', props.row.id)"
              class="action"
              title="Edit"
          >
            <i class="fa fa-pencil-square-o" />
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
      <!--      <div style="position: relative; height: 100px;">-->
      <!--        <button @click="downloadCSVHandler"-->
      <!--                class="btn btn-primary"-->
      <!--                style="position: absolute; top: 50%; right: 1rem; transform: translateY(-50%);">-->
      <!--          Download results CSV-->
      <!--        </button>-->
      <!--      </div>-->
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
import Delete from '../components/Edit/Modals/Delete.vue';
import Alerts from "@/components/Alerts.vue";
import qs from 'qs';
import VueTables from 'vue-tables-2';
import { nextTick } from 'vue';

import ActiveFilters from '../components/Search/ActiveFilters.vue';
import {
  createMultiSelect,
  createMultiMultiSelect,
  createLanguageToggle
} from '@/helpers/formFieldUtils';
import { formatDate, greekFont } from "@/helpers/formatUtil";
import { isLoginError } from "@/helpers/errorUtil";

import { useRequestTracker } from "@/composables/searchAppComposables/useRequestTracker";
import { usePaginationCount } from "@/composables/searchAppComposables/usePaginationCount";
import { useFormValidation } from "@/composables/searchAppComposables/useFormValidation";
import { useSearchFields } from "@/composables/searchAppComposables/useSearchFields";
import { useCollectionManagement } from "@/composables/searchAppComposables/useCollectionManagement";
import { useEditMergeMigrateDelete } from "@/composables/editAppComposables/useEditMergeMigrateDelete";
import CollectionManager from '../components/Search/CollectionManager.vue';
import { constructFilterValues } from "@/helpers/searchAppHelpers/filterUtil";
import { popHistory, pushHistory } from "@/helpers/searchAppHelpers/historyUtil";
import { fetchDependencies } from "@/helpers/searchAppHelpers/fetchDependencies";
import { downloadCSV } from "@/helpers/downloadUtil";
import { useSearchSession } from "@/composables/searchAppComposables/useSearchSession";
import validatorUtil from '@/helpers/validatorUtil';
const props = defineProps({
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
  lemma_mode: ['greek'],
});

if (props.isViewInternal) {
  model.value.text_stem = 'original';
}

const originalModel = ref({});

const tableOptions = ref({
  headings: {
    text: 'Title (T.) / text (matching verses only)',
    comment: 'Comment (matching lines only)',
    lemma: 'Lemma (matching lines in original text only)',
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
  sortable: ['id', 'incipit', 'number_of_occurrences', 'created', 'modified'],
  customFilters: ['filters'],
  rowClassCallback(row) {
    return (row.public == null || row.public) ? '' : 'warning';
  },
});

const submitModel = reactive({
  submitType: 'type',
  type: {},
});

const defaultOrdering = ref('incipit');
const initialized = ref(false);
const noHistory = ref(false);
const tableCancel = ref(false);
const resultTableRef = ref(null);
const aggregation = ref({});
const historyRequest = ref(false);
const typeSearchRef = ref(null);

const schema = ref({
  fields: {},
  groups: [],
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

  fields.lemma_mode = createLanguageToggle('lemma');
  // disable latin
  fields.lemma_mode.values[2].disabled = true;

  fields.lemma = {
    type: 'input',
    inputType: 'text',
    styleClasses: 'greek',
    labelClasses: 'control-label',
    label: 'Lemma',
    model: 'lemma',
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

  [fields.metre_op, fields.metre] = createMultiMultiSelect('Metre');
  [fields.genre_op, fields.genre] = createMultiMultiSelect('Genre');
  [fields.subject_op, fields.subject] = createMultiMultiSelect('Subject');
  [fields.tag_op, fields.tag] = createMultiMultiSelect('Tag');

  fields.translated = createMultiSelect(
      'Translation(s) available?',
      {
        model: 'translated',
      },
      {
        customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
      },
  );

  [
    fields.translation_language_op,
    fields.translation_language,
  ] = createMultiMultiSelect(
      'Translation language',
      {
        dependency: 'translated',
        model: 'translation_language',
      },
  );

  fields.comment_mode = createLanguageToggle('comment');
  fields.comment = {
    type: 'input',
    inputType: 'text',
    label: 'Comment',
    labelClasses: 'control-label',
    model: 'comment',
    validator:validatorUtil.string,
  };

  // Add identifier fields
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

  fields.id = createMultiSelect('DBBE ID', { model: 'id' });
  fields.prev_id = createMultiSelect('Former DBBE ID', { model: 'prev_id' });

  fields.dbbe = createMultiSelect(
      'Text source DBBE?',
      {
        model: 'dbbe',
      },
      {
        customLabel: ({ _id, name }) => (name === 'true' ? 'Yes' : 'No'),
      },
  );

  [fields.acknowledgement_op, fields.acknowledgement] = createMultiMultiSelect(
      'Acknowledgements',
      {
        model: 'acknowledgement',
      },
  );

  if (props.isViewInternal) {
    fields.text_status = createMultiSelect(
        'Text Status',
        {
          model: 'text_status',
          styleClasses: 'has-warning',
        },
    );
    fields.critical_status = createMultiSelect(
        'Editorial Status',
        {
          model: 'critical_status',
          styleClasses: 'has-warning',
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
  Occurrences: {
    depUrl: urls.occurrence_deps_by_type.replace('type_id', submitModel.type.id),
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
  defaultOrdering: ref('incipit'),
  emitFilter: (filters) => VueTables.Event.$emit('vue-tables.filter::filters', filters),
  historyRequest
});
const urlInitialized = ref(false);

watch(() => aggregation.value,async (newAggregation, oldAggregation) => {
  if (!urlInitialized.value && newAggregation && Object.keys(newAggregation).length > 0) {
    initFromURL(newAggregation);
    urlInitialized.value = true;
    await nextTick()
    onValidated(true);

  }
}, { immediate: true });


const {
  notEmptyFields,
  changeTextMode,
  setUpOperatorWatchers,
  onLoaded,
  deleteActiveFilter,
  onDataExtend,
  textSearch,
  commentSearch,
  lemmaSearch
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
  elRef: typeSearchRef,
  onDataExtend
}, 'TypeSearchConfig');

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

const tableColumns = computed(() => {
  const columns = ['id', 'incipit', 'numberOfOccurrences'];
  if (textSearch.value) {
    columns.unshift('text');
  }
  if (commentSearch.value) {
    columns.unshift('comment');
  }
  if (lemmaSearch.value) {
    columns.unshift('lemma');
  }
  if (props.isViewInternal) {
    columns.push('created');
    columns.push('modified');
    columns.push('actions');
    columns.push('c');
  }
  return columns;
});

const handleDeletedActiveFilter = (field) => {
  deleteActiveFilter(field);
  onValidated(true);
};

const requestFunction = async (data) => {
  const params = cleanParams(data);
  startRequest();
  let url = urls['types_search_api'];

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
    return await axiosGet(url, {}, tableCancel, onData, data);
  }

  if (!noHistory) {
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
  );
};

const submitDelete = async () => {
  startRequest();
  deleteModal.value = false;

  try {
    await axios.delete(urls.type_delete.replace('type_id', submitModel.type.id));
    noHistory.value = true;
    resultTableRef.value?.refresh();
    alerts.value.push({ type: 'success', message: 'Type deleted successfully.' });
  } catch (error) {
    alerts.value.push({ type: 'error', message: 'Something went wrong while deleting the type.' });
    console.error(error);
  } finally {
    endRequest();
  }
};

const del = async (row) => {
  submitModel.type = {
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

watch(() => model.value.lemma_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'lemma');
});

watch(() => model.value.text_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'text');
});

watch(() => model.value.comment_mode, (val, oldVal) => {
  changeTextMode(val, oldVal, 'comment');
});

watch(() => model.value.comment, (newValue) => {
  if (newValue && newValue.trim().length > 0) {
    commentSearch.value = true;
  } else {
    commentSearch.value = false;
  }
}, { immediate: true });

watch(
    () => schema.value?.groups,
    async (groups) => {
      if (!groups || !Array.isArray(groups)) return;
      await nextTick();
      const legends = typeSearchRef.value?.$el?.querySelectorAll('.vue-form-generator .collapsible legend') || [];
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
  originalModel.value = JSON.parse(JSON.stringify(model));
  window.onpopstate = (event) => {
    historyRequest.value = popHistory();
    resultTableRef.value?.refresh();
  };
  updateCountRecords();
});
</script>