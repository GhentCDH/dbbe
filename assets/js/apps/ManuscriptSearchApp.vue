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
                    ref="form"
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
                @deletedActiveFilter="deleteActiveFilter"
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
                :url="urls['manuscripts_search_api']"
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
                <a
                    slot="name"
                    slot-scope="props"
                    :href="urls['manuscript_get'].replace('manuscript_id', props.row.id)"
                >
                    {{ props.row.name }}
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
                    v-if="props.row.content"
                    slot="content"
                    slot-scope="props"
                >
                    <!-- set displayContent using a v-for -->
                    <!-- eslint-disable-next-line max-len -->
                    <template v-for="(displayContent, index) in [props.row.content.filter((content) => content['display'])]">
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
                            {{ displayContent[0].name }}
                        </template>
                    </template>
                </template>
                <template
                    slot="occ"
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
                        :href="urls['manuscript_edit'].replace('manuscript_id', props.row.id)"
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
        <deleteModal
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
<script>
import Vue, {computed, getCurrentInstance, onMounted, reactive, ref, watch} from 'vue';
import Delete from '../Components/Edit/Modals/Delete.vue';
import Alerts from "@/Components/Alerts.vue";
import qs from 'qs';
import VueTables from 'vue-tables-2';
import VueFormGenerator from 'vue-form-generator';
import VueCookies from 'vue-cookies';

import ActiveFilters from '../Components/Search/ActiveFilters.vue';


import {createMultiSelect,} from '@/helpers/formFieldUtils';

import {formatDate, greekFont,} from "@/helpers/formatUtil";

import {isLoginError} from "@/helpers/errorUtil";
import fieldCheckboxes from '../Components/FormFields/fieldCheckboxes.vue';

import {useRequestTracker} from "@/composables/abstractSearchComposables/useRequestTracker";
import {usePaginationCount} from "@/composables/abstractSearchComposables/usePaginationCount";
import {useFormValidation} from "@/composables/abstractSearchComposables/useFormValidation";
import {useSearchSession} from "@/composables/useSearchSession";
import {useManuscriptSearchSchema} from "@/composables/useManuscriptSearch/useManuscriptSearchSchema";
import {useEditMergeMigrateDelete} from "@/composables/useEditMergeMigrateDelete";
import {useSearchFields} from "@/composables/abstractSearchComposables/useSearchFields";
import {useCollectionManagement} from "@/composables/abstractSearchComposables/useCollectionManagement";
import CollectionManager from '../Components/Search/CollectionManager.vue';
import {constructFilterValues} from "@/helpers/abstractSearchHelpers/filterUtil";
import {popHistory, pushHistory} from "@/helpers/abstractSearchHelpers/historyUtil";
import {fetchDependencies} from "@/helpers/fetchDependencies";
import {downloadCSV} from "@/helpers/downloadUtil";

Vue.use(VueCookies);
Vue.use(VueFormGenerator);
Vue.component('DeleteModal', Delete);
Vue.component('CollectionManager', CollectionManager);
Vue.component('FieldCheckboxes', fieldCheckboxes);

export default {
  components: { ActiveFilters, Alerts},
  props: {
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
  },

  setup(props) {
    const urls = JSON.parse(props.initUrls);
    const data= JSON.parse(props.initData);
    const identifiers= JSON.parse(props.initIdentifiers);
    const managements=JSON.parse(props.initManagements);
    const formOptions = ref({
      validateAfterLoad: true,
          validateAfterChanged: true,
        validationErrorClass: 'has-error',
        validationSuccessClass: 'success',
    })
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
    });
    const originalModel=ref({})
    const tableOptions = ref({
      headings: {
        comment: 'Comment (matching lines only)',
      },
      filterable: false,
      orderBy: {
        column: 'name',
      },
      perPage: 25,
      perPageValues: [25, 50, 100],
      sortable: ['name', 'date', 'occ', 'created', 'modified'],
      customFilters: ['filters'],
      rowClassCallback(row) {
        return row.public == null || row.public ? '' : 'warning';
      },
    });
    const submitModel = reactive({
      submitType: 'manuscript',
      manuscript: {},
    });
    const defaultOrdering = ref('name');
    const defaultConfig = ref({ groupIsOpen: [] });
    const config = ref({ groupIsOpen: [] });
    const initialized = ref(false)
    const noHistory = ref(false)
    const tableCancel = ref(false)
    const resultTableRef = ref(null);
    const aggregation = ref({});
    const instance = getCurrentInstance();
    const vm = instance?.proxy;
    const idList = [];
    const historyRequest = ref(false);


    for (const identifier of JSON.parse(props.initIdentifiers)) {
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
    const { schema } = useManuscriptSearchSchema(idList);

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
                if (!vm.multiple || field.multi === true) {
                  res[field.model] = field;
                }
              });
            }
          });
        }
      }

      if (props.isViewInternal) {
        res.public = createMultiSelect('Public', {
          styleClasses: 'has-warning',
        }, {
          customLabel: ({ name }) => name === 'true' ? 'Public only' : 'Internal only',
        });

        res.management = createMultiSelect('Management collection', {
          model: 'management',
          styleClasses: 'has-warning',
        });

        res.management_inverse = {
          type: 'checkbox',
          styleClasses: 'has-warning',
          label: 'Inverse management collection selection',
          labelClasses: 'control-label',
          model: 'management_inverse',
        };
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

    const tableColumns = computed(() => {
      const columns = ['name', 'date', 'content'];
      if (vm.commentSearch) {
        columns.unshift('comment');
      }
      if (props.isViewInternal) {
        columns.push('occ', 'created', 'modified', 'actions', 'c');
      }
      return columns;
    });

    const { countRecords, updateCountRecords } = usePaginationCount(resultTableRef);

    const {
      openRequests,
      alerts,
      startRequest,
      endRequest,
    } = useRequestTracker();



    const session = useSearchSession(vm, 'ManuscriptSearchConfig');

    const onData = (data) => session.onData(data, onDataExtend);
    watch(() => model.value.text_mode, (val, oldVal) => {
      changeTextMode(val, oldVal, 'text');
    });
    watch(() => model.value.comment_mode, (val, oldVal) => {
      changeTextMode(val, oldVal, 'comment');
    });

    const requestFunction= async (data) => {
      const params = { ...data };
      delete params.query;
      delete params.byColumn;
      if (!('orderBy' in params)) {
        delete params.ascending;
      }
      if (!params.filters) {
        delete params.filters;
      }

      startRequest();
      const handleError = (error) => {
        endRequest();
        alerts.push({
          type: 'error',
          message:
              'Something went wrong while processing your request. Please verify your input is valid.',
        });
        console.error(error);
        return {
          data: {
            data: data,
            count: count,
          },
        };
      };

      const axiosGet = async (url, options = {}) => {
        if (openRequests > 1 && tableCancel != null) {
          tableCancel('Operation canceled by newer request');
        }

        try {
          const response = await axios.get(url, {
            cancelToken: new axios.CancelToken((c) => {
              tableCancel.value = c;
            }),
            ...options,
          });
          alerts.value = [];
          onData(response.data);
          return response;
        } catch (error) {
          if (axios.isCancel(error)) {
            return {
              data: {
                data: data,
                count: count,
              },
            };
          }
          return handleError(error);
        }
      };
      let url = urls['manuscripts_search_api'];

      if (!initialized) {
        onData(data);
        return {
          data: {
            data: data.data,
            count: data.count,
          },
        };
      }

      if (!actualRequest) {
        return {
          data: {
            data: data,
            count: count,
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

      return await axiosGet(url, {
        params,
        paramsSerializer: qs.stringify,
      });
    }

    tableOptions.value.requestFunction = requestFunction;
    const {
      delDependencies,
      deleteModal,
    } = useEditMergeMigrateDelete(props.initUrls, props.initData)

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

    onMounted(() => {
      session.setupCollapsibleLegends();
      vm.$on('config-changed', session.handleConfigChange(schema.value));
      updateCountRecords();
      initFromURL(aggregation.value);
      originalModel.value = JSON.parse(JSON.stringify(model));
      window.onpopstate = (event) => {
        historyRequest.value = popHistory();
        resultTableRef.value?.refresh();
      };
      updateCountRecords();
    });

    async function submitDelete() {
      startRequest();
      deleteModal.value = false;

      try {
        await axios.delete(
            urls.manuscript_delete.replace('manuscript_id', submitModel.manuscript.id)
        );
        noHistory.value = true;
        resultTableRef.value?.refresh();
        alerts.value.push({ type: 'success', message: 'Manuscript deleted successfully.' });
      } catch (error) {
        alerts.value.push({ type: 'error', message: 'Something went wrong while deleting the manuscript.' });
        console.error(error);
      } finally {
        endRequest();
      }
    }

    async function del(row) {
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
    }

    function modelUpdated(value, fieldName) {
      lastChangedField.value = fieldName;
    }

    function resetAllFilters() {
      model.value = JSON.parse(JSON.stringify(originalModel));
      onValidated(true);
    }

    async function downloadCSVHandler() {
      try {
        await downloadCSV(urls);
      } catch (error) {
        console.error(error);
        alerts.value.push({ type: 'error', message: 'Error downloading CSV.' });
      }
    }

    const {
      onValidated,
      lastChangedField,
      actualRequest,
      initFromURL
    } = useFormValidation({
      model,
      fields,
      resultTableRef,
      defaultOrdering: ref('name'),
      emitFilter: (filters) => VueTables.Event.$emit('vue-tables.filter::filters', filters),
      historyRequest
    });

    const {
      notEmptyFields,
      changeTextMode,
      setUpOperatorWatchers,
      onLoaded,
      deleteActiveFilter,
        onDataExtend
    } = useSearchFields(model, schema, fields, aggregation, {
      multiple: true,
      updateCountRecords,
      initFromURL,
      endRequest,
      historyRequest
    });
    setUpOperatorWatchers();
    return {
      formOptions,
      model,
      initialized,
      fields,
      data,
      urls,
      identifiers,
      managements,
      schema,
      tableOptions,
      submitModel,
      defaultOrdering,
      defaultConfig,
      config,
      resultTableRef,
      aggregation,
      tableColumns,

      del,
      submitDelete,
      deleteModal,
      delDependencies,
      resetAllFilters,
      modelUpdated,
      depUrls,
      collectionToggleAll,
      clearCollection,
      addManagementsToSelection,
      removeManagementsFromSelection,
      addManagementsToResults,
      removeManagementsFromResults,

      downloadCSVHandler,

      openRequests,
      startRequest,
      endRequest,
      alerts,
      formatDate,
      greekFont,
      session,
      onData,
      countRecords,
      updateCountRecords,
      onValidated,
      lastChangedField,
      initFromURL,
      historyRequest,
      notEmptyFields,
      deleteActiveFilter,
      onLoaded,
      collectionArray
    };
  },

};
</script>