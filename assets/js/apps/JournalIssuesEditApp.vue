<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts :alerts="alerts" @dismiss="(i) => alerts.splice(i, 1)" />
      <Panel header="Edit journal issues">
        <EditListRow
            :schema="schema"
            :model="model"
            name="origin"
            :conditions="{
            add: true,
            edit: model.journalIssue,
            del: model.journalIssue,
          }"
            @add="edit(true)"
            @edit="edit()"
            @del="del()"
        />
      </Panel>
      <div v-if="openRequests > 0" class="loading-overlay">
        <div class="spinner" />
      </div>
    </article>

    <Edit
        ref="editModalRef"
        :show="editModalValue"
        :schema="editSchema"
        :submit-model="submitModel"
        :original-submit-model="originalSubmitModel"
        :alerts="editAlerts"
        @cancel="cancelEdit"
        @reset="resetEdit(submitModel)"
        @confirm="submitEdit"
        @dismiss-alert="(i) => editAlerts.splice(i, 1)"
    />
    <Delete
        :show="deleteModal"
        :del-dependencies="delDependencies"
        :submit-model="submitModel"
        :alerts="deleteAlerts"
        @cancel="cancelDelete"
        @confirm="submitDelete"
        @dismiss-alert="(i) => deleteAlerts.splice(i, 1)"
    />
  </div>
</template>

<script setup>
import { onMounted, ref, reactive, watch } from 'vue';
import qs from 'qs';
import axios from 'axios';
import VueFormGenerator from 'vue-form-generator';
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils';
import { isLoginError } from '@/helpers/errorUtil';
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete';
import Alerts from '@/Components/Alerts.vue';
import EditListRow from '@/Components/Edit/EditListRow.vue';
import Edit from '@/Components/Edit/Modals/Edit.vue';
import Merge from '@/Components/Edit/Modals/Merge.vue';
import Delete from '@/Components/Edit/Modals/Delete.vue';
import Panel from '@/Components/Edit/Panel.vue';

const props = defineProps({
  initUrls: String,
  initData: String,
});

const editModalRef = ref(null);
const revalidate = ref(false);

const {
  urls,
  values,
  alerts,
  editAlerts,
  deleteAlerts,
  delDependencies,
  deleteModal,
  editModalValue,
  originalSubmitModel,
  openRequests,
  resetEdit,
  deleteDependencies,
  cancelEdit,
  cancelDelete,
} = useEditMergeMigrateDelete(props.initUrls, props.initData, ref({}));

const model = reactive({ journalIssue: null });

const initDataParsed = JSON.parse(props.initData);
const journals = initDataParsed.journals;
const journalIssues = initDataParsed.journalIssues;
values.value = journalIssues;

const schema = reactive({
  fields: {
    journalIssues: createMultiSelect('Journal Issue', {
      model: 'journalIssue',
      dependency: 'journal',
      required: true,
      validator: VueFormGenerator.validators.required,
      values: journalIssues,
    }),
  },
});

function yearOrForthcoming() {
  const ji = submitModel['journal issue'];
  if (
      (!ji.year && !ji.forthcoming) ||
      (ji.year && ji.forthcoming)
  ) {
    return 'Exactly one of the fields "Year" or "Forthcoming" is required.';
  }
  return true;  // or null, depending on what VFG expects
}




const editSchema = reactive({
  fields: {
    journal: createMultiSelect('Journal', {
      model: 'journal issue.journal',
      required: true,
      validator: VueFormGenerator.validators.required,
      values: journals,
    }),
    year: {
      type: 'input',
      inputType: 'text',
      label: 'Year',
      labelClasses: 'control-label',
      model: 'journal issue.year',
      validator: [yearOrForthcoming],
    },
    forthcoming: {
      type: 'checkbox',
      label: 'Forthcoming',
      labelClasses: 'control-label',
      model: 'journal issue.forthcoming',
      validator: [yearOrForthcoming],
    },
    series: {
      type: 'input',
      inputType: 'text',
      label: 'Series',
      labelClasses: 'control-label',
      model: 'journal issue.series',
    },
    volume: {
      type: 'input',
      inputType: 'text',
      label: 'Volume',
      labelClasses: 'control-label',
      model: 'journal issue.volume',
    },
    number: {
      type: 'input',
      inputType: 'text',
      label: 'Number',
      labelClasses: 'control-label',
      model: 'journal issue.number',
    },
  },
});

const submitModel = reactive({
  submitType: 'journal issue',
  'journal issue': {},
});

const depUrls = ref({
  Articles: {
    depUrl: '',
    url: '',
    urlIdentifier: 'article_id',
  },
});

watch(() => submitModel['journal issue'].id, (newId) => {
  if (newId && urls.article_deps_by_journal_issue) {
    depUrls.value.Articles.depUrl = urls.article_deps_by_journal_issue.replace('journal_issue_id', newId);
    depUrls.value.Articles.url = urls.article_get;
  }
});


watch(() => submitModel['journal issue'].year, (newVal) => {
  if (newVal === '') {
    submitModel['journal issue'].year = null;
    revalidate.value = true;
    if (editModalRef.value) editModalRef.value.validate();
    revalidate.value = false;
  } else if (!revalidate.value) {
    editModalRef.value?.validate();
  }
});

watch(() => submitModel['journal issue'].volume, (newVal) => {
  if (newVal === '') {
    submitModel['journal issue'].volume = null;
    revalidate.value = true;
    editModalRef.value?.validate();
    revalidate.value = false;
  } else if (!revalidate.value) {
    editModalRef.value?.validate();
  }
});

watch(() => submitModel['journal issue'].series, (newVal) => {
  if (newVal === '') {
    submitModel['journal issue'].series = null;
    revalidate.value = true;
    editModalRef.value?.validate();
    revalidate.value = false;
  } else if (!revalidate.value) {
    editModalRef.value?.validate();
  }
});

watch(() => submitModel['journal issue'].number, (newVal) => {
  if (newVal === '') {
    submitModel['journal issue'].number = null;
    revalidate.value = true;
    editModalRef.value?.validate();
    revalidate.value = false;
  } else if (!revalidate.value) {
    editModalRef.value?.validate();
  }
});

function revalidateForm() {
  if (editModalRef.value) {
    editModalRef.value.validate();
  }
}

onMounted(() => {
  schema.fields.journalIssues.values = values.value || [];

  // Parse URL query param for initial selection
  const params = qs.parse(window.location.href.split('?', 2)[1] || '');
  if (!isNaN(params.id)) {
    const found = values.value.find(v => v.id === parseInt(params.id));
    if (found) model.journalIssue = JSON.parse(JSON.stringify(found));
  }

  window.history.pushState({}, null, window.location.href.split('?', 2)[0]);
  enableField(schema.fields.journalIssues, model);
});

function edit(add = false) {
  if (add) {
    submitModel['journal issue'] = {
      journal: null,
      year: null,
      forthcoming: false,
      series: null,
      volume: null,
      number: null,
    };
  } else {
    submitModel['journal issue'] = JSON.parse(JSON.stringify(model.journalIssue));
  }

  editSchema.fields.journal.values = journals;
  enableField(editSchema.fields.journal);

  Object.assign(originalSubmitModel, JSON.parse(JSON.stringify(submitModel)));
  editModalValue.value = true;
}

function del() {
  submitModel['journal issue'] = JSON.parse(JSON.stringify(model.journalIssue));
  deleteDependencies();
}

function submitEdit() {
  editModalValue.value = false;
  openRequests.value++;

  const curr = submitModel['journal issue'];
  const orig = originalSubmitModel['journal issue'];
  const data = {};

  for (const key in curr) {
    if (!curr.id || curr[key] !== orig[key]) {
      data[key] = curr[key];
    }
  }

  const request = curr.id
      ? axios.put(urls.journal_issue_put.replace('journal_issue_id', curr.id), data)
      : axios.post(urls.journal_issue_post, data);

  request
      .then((response) => {
        submitModel['journal issue'] = response.data;
        update();
        editAlerts.value = [];
        alerts.value.push({
          type: 'success',
          message: curr.id ? 'Update successful.' : 'Addition successful.',
        });
        openRequests.value--;
      })
      .catch((error) => {
        openRequests.value--;
        editModalValue.value = true;
        editAlerts.value.push({
          type: 'error',
          message: `Something went wrong while ${curr.id ? 'updating' : 'adding'} the journal issue.`,
          login: isLoginError(error),
        });
        console.error(error);
      });
}

function submitDelete() {
  deleteModal.value = false;
  openRequests.value++;

  axios
      .delete(urls.journal_issue_delete.replace('journal_issue_id', submitModel['journal issue'].id))
      .then(() => {
        submitModel['journal issue'] = null;
        update();
        deleteAlerts.value = [];
        alerts.value.push({ type: 'success', message: 'Deletion successful.' });
        openRequests.value--;
      })
      .catch((error) => {
        openRequests.value--;
        deleteModal.value = true;
        deleteAlerts.value.push({
          type: 'error',
          message: 'Something went wrong while deleting the journal.',
          login: isLoginError(error),
        });
        console.error(error);
      });
}

function update() {
  openRequests.value++;
  axios
      .get(urls.journal_issues_get)
      .then((response) => {
        values.value = response.data;
        schema.fields.journalIssues.values = Array.isArray(values.value) ? values.value : [];
        model.journalIssue = JSON.parse(JSON.stringify(submitModel['journal issue']));
        openRequests.value--;
      })
      .catch((error) => {
        openRequests.value--;
        alerts.value.push({
          type: 'error',
          message: 'Something went wrong while renewing the journal issue data.',
          login: isLoginError(error),
        });
        console.error(error);
      });
}
</script>
