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
import { createMultiSelect, enableField } from '@/helpers/formFieldUtils';
import { isLoginError } from '@/helpers/errorUtil';
import { useEditMergeMigrateDelete } from '@/composables/editAppComposables/useEditMergeMigrateDelete';
import Alerts from '@/components/Alerts.vue';
import EditListRow from '@/components/Edit/EditListRow.vue';
import Edit from '@/components/Edit/Modals/Edit.vue';
import Delete from '@/components/Edit/Modals/Delete.vue';
import Panel from '@/components/Edit/Panel.vue';
import validatorUtil from "@/helpers/validatorUtil";

const props = defineProps({
  initUrls: String,
  initData: String,
});

const editModalRef = ref(null);
const revalidate = ref(false);

const model = reactive({ journalIssue: null });

const submitModel = reactive({
  submitType: 'journal issue',
  'journal issue': {},
});

// Define reactive dependency URLs like in your journals app
const depUrls = reactive({
  get 'Articles'() {
    // Handle null/undefined case more defensively
    if (!submitModel || !submitModel['journal issue'] || !submitModel['journal issue'].id) {
      return { depUrl: '' };
    }

    const journalIssueId = submitModel['journal issue'].id;
    return {
      depUrl: urls['article_deps_by_journal_issue']?.replace('journal_issue_id', journalIssueId) || '',
      url: urls['article_get'],
      urlIdentifier: 'article_id',
    }
  }
});

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
} = useEditMergeMigrateDelete(props.initUrls, props.initData, depUrls);

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
      validator: validatorUtil.required,
      values: journalIssues,
    }),
  },
});

function yearOrForthcoming(value, field, model) {
  const ji = model;

  // Don't validate if this is a new item (no ID) and both fields are empty/null
  if (!ji.id && (!ji.year && !ji.forthcoming)) {
    return true; // Allow empty state for new items
  }

  if (
      (!ji.year && !ji.forthcoming) ||
      (ji.year && ji.forthcoming)
  ) {
    return 'Exactly one of the fields "Year" or "Forthcoming" is required.';
  }
  return true;
}

const editSchema = reactive({
  fields: {
    journal: createMultiSelect('Journal', {
      model: 'journal issue.journal',
      required: true,
      validator: (value, field, model) => {
        // Don't validate required fields for new items until user interaction
        if (!model.id && !value) return true;
        return validatorUtil.required(value, field, model);
      },
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

// Form field watchers for validation
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
  // Temporarily disable validation during setup
  const wasRevalidating = revalidate.value;
  revalidate.value = true;

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

  // Re-enable validation after a short delay to allow the modal to render
  setTimeout(() => {
    revalidate.value = wasRevalidating;
  }, 100);

  editModalValue.value = true;
}

function del() {
  if (!model.journalIssue) {
    alerts.value.push({ type: 'error', message: 'No journal issue selected for deletion.' });
    return;
  }

  submitModel['journal issue'] = JSON.parse(JSON.stringify(model.journalIssue));
  deleteDependencies();
}

async function submitEdit() {
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

  try {
    let response;
    if (curr.id) {
      response = await axios.put(urls.journal_issue_put.replace('journal_issue_id', curr.id), data);
    } else {
      response = await axios.post(urls.journal_issue_post, data);
    }

    submitModel['journal issue'] = response.data;
    await update();
    editAlerts.value = [];
    alerts.value.push({
      type: 'success',
      message: curr.id ? 'Update successful.' : 'Addition successful.',
    });
  } catch (error) {
    editModalValue.value = true;
    editAlerts.value.push({
      type: 'error',
      message: `Something went wrong while ${curr.id ? 'updating' : 'adding'} the journal issue.`,
      login: isLoginError(error),
    });
    console.error(error);
  } finally {
    openRequests.value--;
  }
}

async function submitDelete() {
  deleteModal.value = false;
  openRequests.value++;

  try {
    await axios.delete(urls.journal_issue_delete.replace('journal_issue_id', submitModel['journal issue'].id));

    // Reset both submitModel and model properly
    submitModel['journal issue'] = {
      journal: null,
      year: null,
      forthcoming: false,
      series: null,
      volume: null,
      number: null,
    };
    model.journalIssue = null;

    await update();
    deleteAlerts.value = [];
    alerts.value.push({ type: 'success', message: 'Deletion successful.' });
  } catch (error) {
    deleteModal.value = true;
    deleteAlerts.value.push({
      type: 'error',
      message: 'Something went wrong while deleting the journal issue.',
      login: isLoginError(error),
    });
    console.error(error);
  } finally {
    openRequests.value--;
  }
}

async function update() {
  openRequests.value++;
  try {
    const response = await axios.get(urls.journal_issues_get);
    values.value = response.data;
    schema.fields.journalIssues.values = Array.isArray(values.value) ? values.value : [];

    if (submitModel['journal issue']) {
      model.journalIssue = JSON.parse(JSON.stringify(submitModel['journal issue']));
    }
  } catch (error) {
    alerts.value.push({
      type: 'error',
      message: 'Something went wrong while renewing the journal issue data.',
      login: isLoginError(error),
    });
    console.error(error);
  } finally {
    openRequests.value--;
  }
}
</script>