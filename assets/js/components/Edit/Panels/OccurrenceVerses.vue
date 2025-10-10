<template>
  <Panel :header="header">
    <vue-form-generator
        ref="generalFormRef"
        :schema="generalSchema"
        :model="model"
        :options="formOptions"
        @validated="validated"
    />
    <h6>Preview</h6>
    <table
        v-if="model.verses"
        class="table greek verses"
    >
      <tbody>
      <tr
          v-for="(individualVerse, index) in model.verses"
          :key="index"
      >
        <td
            class="line-number"
            :data-line-number="index + 1"
        />
        <td class="verse">

          <a v-if="individualVerse.groupId"
          :href="urls['verse_variant_get'].replace('verse_variant_id', individualVerse.groupId)"
          >
          {{ individualVerse.verse }}
          <i class="fa fa-link pull-right" />
          </a>

          <a v-else-if="individualVerse.linkVerses"
          href="#"
          >
          {{ individualVerse.verse }}
          <i class="fa fa-link pull-right" />
          </a>
          <template v-else>
            {{ individualVerse.verse }}
          </template>
        </td>
      </tr>
      </tbody>
    </table>
    <btn @click="addText">
      <i class="fa fa-plus" />&nbsp;Add verses as full text
    </btn>
    <h6>Edit verses individually</h6>
    <div ref="versesContainer">
      <div
          v-for="(individualVerse, index) in model.verses"
          :key="individualVerse.order"
          class="panel panel-default draggable-item greek"
      >
        <div class="panel-body row">
          <div class="col-xs-1">
            <i class="fa fa-arrows draggable-icon" style="cursor: move;" />
          </div>
          <div class="col-xs-9">
            {{ individualVerse.verse }}
          </div>
          <div class="col-xs-2 text-right">
            <a v-if="individualVerse.linkVerses || individualVerse.groupId"
               href="#"
               title="Display links"
               class="action"
               @click.prevent="displayLinks(index)"
            >
              <i class="fa fa-link" />
            </a>

            <a href="#"
               title="Edit"
               class="action"
               @click.prevent="editVerse(index)"
            >
              <i class="fa fa-pencil-square-o" />
            </a>

            <a href="#"
               title="Delete"
               class="action"
               @click.prevent="delVerse(index)"
            >
              <i class="fa fa-trash-o" />
            </a>
          </div>
        </div>
      </div>
    </div>
    <btn @click="addVerse">
      <i class="fa fa-plus" />&nbsp;Add a single verse
    </btn>

    <!-- Rest of modals unchanged -->
    <!-- Links Modal -->
    <modal
        v-if="verse"
        v-model="linksModal"
        size="lg"
        :footer=null
        auto-focus
    >
      <template #header>
        <h4 class="modal-title">
          <span>Linked verses for verse "<span class="greek">{{ verse.verse }}</span>" ({{ verse.index + 1 }})</span>
        </h4>
      </template>

      <alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <verseTable
          :link-verses="tableVerses"
          :urls="urls"
      />
    </modal>

    <!-- Add Text Modal -->
    <modal
        v-model="addTextModal"
        size="lg"
        auto-focus
    >
      <template #header>
        <h4 class="modal-title">
          Add text
        </h4>
      </template>

      <vue-form-generator
          ref="addTextFormRef"
          :schema="addTextSchema"
          :model="textModel"
          :options="formOptions"
          @validated="addTextValidated"
      />

      <template #footer>
        <btn @click="addTextModal = false">Cancel</btn>
        <btn
            type="success"
            :disabled="!addTextIsValid"
            @click="submitAddText"
        >
          Add
        </btn>
      </template>
    </modal>

    <!-- Edit Verse Modal -->
    <modal
        v-if="verse != null && verse.linkVerses != null"
        v-model="editVerseModal"
        size="lg"
        auto-focus
        :backdrop="null"
    >
      <template #header>
        <h4 class="modal-title">
          Edit verse
        </h4>
      </template>

      <alerts
          :alerts="alerts"
          @dismiss="alerts.splice($event, 1)"
      />
      <div class="pbottom-default">
        <vue-form-generator
            ref="editVerseFormRef"
            :schema="editVerseSchema"
            :model="verse"
            :options="formOptions"
        />
        <btn
            v-if="verse.groupId"
            @click="updateText"
        >
          Update text
        </btn>
        <btn
            v-if="verse.groupId"
            @click="updateTextRemoveLink"
        >
          Update text and remove link(s)
        </btn>
        <btn
            v-if="!(verse.groupId)"
            @click="updateText"
        >
          Update text without linking
        </btn>
      </div>
      <h6>Linked verses</h6>
      <verseTable
          :link-verses="tableVerses"
          :linked-groups="linkedGroups"
          :linked-verses="linkedVerses"
          :urls="urls"
          :edit="true"
          @groupToggle="groupToggle"
          @verseToggle="verseToggle"
      />
      <btn
          v-if="verse.linkVerses.length !== 0"
          type="success"
          @click="updateTextSetLinks"
      >
        Update text and update linked verses
      </btn>
      <btn
          v-else
          type="success"
          @click="updateTextSetLinks"
      >
        Update text and create a new link group for this single verse
      </btn>
      <div class="row">
        <div class="col-xs-11">
          <vue-form-generator
              ref="searchVerseFormRef"
              :schema="searchVerseSchema"
              :model="search"
              :options="formOptions"
          />
        </div>
        <div class="col-xs-1">
          <btn
              :disabled="search == null || search.search == null || search.search === ''"
              style="margin-top: 1.3em;"
              @click="searchVerseLinks"
          >
            <i class="fa fa-search" />
          </btn>
        </div>
      </div>
      <verseTable
          v-if="linkableVerses != null"
          :link-verses="linkableVerses"
          :linked-groups="linkedGroups"
          :linked-verses="linkedVerses"
          :urls="urls"
          :edit="true"
          @groupToggle="groupToggle"
          @verseToggle="verseToggle"
      />

      <template #footer>
        <btn @click="editVerseModal = false">Cancel</btn>
      </template>
    </modal>

    <!-- Delete Verse Modal -->
    <modal
        v-if="verse"
        v-model="delVerseModal"
        auto-focus
    >
      <template #header>
        <h4 class="modal-title">
          Delete verse
        </h4>
      </template>

      Are you sure you want to delete verse "<span class="greek">{{ verse.verse }}</span>"?

      <template #footer>
        <btn @click="delVerseModal = false">Cancel</btn>
        <btn
            type="danger"
            @click="submitDelVerse"
        >
          Delete
        </btn>
      </template>
    </modal>
  </Panel>
</template>

<script setup>
import { ref, computed, watch, nextTick, inject } from 'vue';
import axios from 'axios';
import Alerts from '@/components/Alerts.vue';
import Panel from '../Panel.vue';
import VerseTable from './Components/VerseTable.vue';
import { disableFields as disableFieldsHelper, enableFields as enableFieldsHelper } from '@/helpers/formFieldUtils';
import validatorUtil from '@/helpers/validatorUtil';
const props = defineProps({
  urls: {
    type: Object,
    default: () => ({}),
  },
  header: {
    type: String,
    default: '',
  },
  links: {
    type: Array,
    default: () => [],
  },
  model: {
    type: Object,
    default: () => ({}),
  },
  reloads: {
    type: Array,
    default: () => [],
  },
  values: {
    type: Array,
    default: () => [],
  },
  keys: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['validated', 'reload']);

// Inject parent methods if available (provide defaults if not available)
const parentOpenRequests = inject('openRequests', ref(0));
const parentIsLoginError = inject('isLoginError', () => false);

// Refs
const generalFormRef = ref(null);
const addTextFormRef = ref(null);
const editVerseFormRef = ref(null);
const searchVerseFormRef = ref(null);

const linksModal = ref(null);
const addTextModal = ref(null);
const editVerseModal = ref(null);
const delVerseModal = ref(null);
const addTextIsValid = ref(false);
const textModel = ref({});
const verse = ref(null);
const search = ref({ search: null });
const linkableVerses = ref(null);
const alerts = ref([]);
const oldGroups = ref({});
const changes = ref([]);
const isValid = ref(true);
const originalModel = ref({});

// Form options
const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
};

// Schemas
const generalSchema = {
  fields: {
    incipit: {
      type: 'input',
      inputType: 'text',
      label: 'Incipit',
      labelClasses: 'control-label',
      styleClasses: 'greek',
      model: 'incipit',
      required: true,
      validator: validatorUtil.string,
    },
    title: {
      type: 'input',
      inputType: 'text',
      label: 'Title',
      labelClasses: 'control-label',
      styleClasses: 'greek',
      model: 'title',
      validator: validatorUtil.string,
    },
    numberOfVerses: {
      type: 'input',
      inputType: 'number',
      label: 'Number of verses',
      labelClasses: 'control-label',
      model: 'numberOfVerses',
      validator: validatorUtil.number,
      hint: 'Should be left blank if equal to the number of verses listed below. A "0" (without quotes) should be input when the number of verses is unknown.',
    },
  },
};

const addTextSchema = {
  fields: {
    text: {
      type: 'textArea',
      label: 'Text',
      labelClasses: 'control-label',
      styleClasses: 'greek',
      model: 'text',
      rows: 10,
      validator: validatorUtil.string,
    },
  },
};

const editVerseSchema = {
  fields: {
    verse: {
      type: 'input',
      inputType: 'text',
      label: 'Verse',
      labelClasses: 'control-label',
      styleClasses: 'greek',
      model: 'verse',
      required: true,
      validator: [validatorUtil.string, validatorUtil.required],
    },
  },
};

const searchVerseSchema = {
  fields: {
    search: {
      type: 'input',
      inputType: 'text',
      label: 'Search linkable verses',
      labelClasses: 'control-label',
      styleClasses: 'greek',
      model: 'search',
      validator: validatorUtil.string,
    },
  },
};

// Computed
const tableVerses = computed(() => {
  if (verse.value == null || verse.value.linkVerses == null) {
    return [];
  }

  const results = [];

  for (const v of verse.value.linkVerses) {
    if (v.groupId == null) {
      results.push({
        group: [v],
      });
    } else {
      let existing = false;
      for (const result of results) {
        if (v.groupId === result.group_id) {
          result.group.push(v);
          existing = true;
          break;
        }
      }
      if (!existing) {
        results.push({
          group_id: v.groupId,
          group: [v],
        });
      }
    }
  }

  return results;
});

const linkedGroups = computed(() => {
  if (verse.value == null || verse.value.linkVerses == null) {
    return [];
  }

  const result = [];

  for (const v of verse.value.linkVerses) {
    if (v.groupId != null && !result.includes(v.groupId)) {
      result.push(v.groupId);
    }
  }

  return result;
});

const linkedVerses = computed(() => {
  if (verse.value == null || verse.value.linkVerses == null) {
    return [];
  }

  const result = [];

  for (const v of verse.value.linkVerses) {
    if (v.groupId == null) {
      result.push(v.id);
    }
  }

  return result;
});

const maxOrder = computed(() => {
  if (!props.model.verses || props.model.verses.length === 0) {
    return 0;
  }
  return Math.max(...props.model.verses.map(v => v.order));
});

// Methods
const getVerseIndex = (verse) => {
  return props.model.verses.findIndex(v => v.order === verse.order);
};

const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model));
};

const validate = () => {
  generalFormRef.value?.validate();
};

const displayVerses = (verses) => {
  if (verses == null) {
    return [];
  }

  const result = [];
  for (const v of verses) {
    let display = '<span class="greek">' + v.verse + '</span>';
    if (v.linkVerses != null) {
      display += ' <strong>(new linked verses)</strong>';
    } else if (v.groupId != null) {
      display += ' (linked verses)';
    }
    result.push(display);
  }
  return result;
};

const calcChanges = () => {
  changes.value = [];

  for (const key of Object.keys(props.model)) {
    if (
        JSON.stringify(props.model[key]) !== JSON.stringify(originalModel.value[key]) &&
        !(props.model[key] == null && originalModel.value[key] == null)
    ) {
      switch (key) {
        case 'incipit':
          changes.value.push({
            key: 'incipit',
            label: 'Incipit',
            old: originalModel.value.incipit,
            new: props.model.incipit,
            value: props.model.incipit,
          });
          break;
        case 'title':
          changes.value.push({
            key: 'title',
            label: 'title',
            old: originalModel.value.title,
            new: props.model.title,
            value: props.model.title,
          });
          break;
        case 'numberOfVerses':
          changes.value.push({
            key: 'numberOfVerses',
            label: 'Number of Verses',
            old: originalModel.value.numberOfVerses,
            new: props.model.numberOfVerses,
            value: props.model.numberOfVerses,
          });
          break;
        case 'verses':
          changes.value.push({
            key: 'verses',
            label: 'Verses',
            old: displayVerses(originalModel.value.verses),
            new: displayVerses(props.model.verses),
            value: props.model.verses,
          });
          break;
      }
    }
  }
};

const validated = (valid, errors) => {
  isValid.value = valid;
  calcChanges();
  emit('validated', valid, errors, {
    changes: changes.value,
    isValid: isValid.value,
  });
};

const addTextValidated = (valid, errors) => {
  addTextIsValid.value = valid;
};

const setVerse = (index) => {
  return new Promise((resolve, reject) => {
    const v = JSON.parse(JSON.stringify(props.model.verses[index]));
    v.index = index;

    if (props.model.verses[index].linkVerses != null) {
      verse.value = JSON.parse(JSON.stringify(v));
      return resolve();
    }

    if (oldGroups.value[v.groupId] != null) {
      v.linkVerses = JSON.parse(JSON.stringify(oldGroups.value[v.groupId]));
      verse.value = JSON.parse(JSON.stringify(v));
      return resolve();
    }

    if (v.groupId != null) {
      if (typeof parentOpenRequests.value === 'number') {
        parentOpenRequests.value++;
      }
      axios
          .get(props.urls['verse_variant_get'].replace('verse_variant_id', v.groupId))
          .then((response) => {
            let linkVerses = response.data;
            // remove current verse
            if (v.id != null) {
              linkVerses = linkVerses.filter(linkVerse => linkVerse.id !== v.id);
            }
            oldGroups.value[v.groupId] = linkVerses;
            v.linkVerses = linkVerses;
            verse.value = JSON.parse(JSON.stringify(v));
            if (typeof parentOpenRequests.value === 'number') {
              parentOpenRequests.value--;
            }
            return resolve();
          })
          .catch((error) => {
            console.log(error);
            alerts.value.push({
              type: 'error',
              message: 'Something went wrong while searching for linked verses.',
              login: parentIsLoginError(error),
            });
            if (typeof parentOpenRequests.value === 'number') {
              parentOpenRequests.value--;
            }
            return reject(error);
          });
    } else {
      v.linkVerses = [];
      verse.value = JSON.parse(JSON.stringify(v));
      return resolve();
    }
  });
};

const displayLinks = (index) => {
  setVerse(index).then(() => {
    linksModal.value = true;
  });
};

const addText = () => {
  textModel.value = { text: '' };
  addTextModal.value = true;
};

const submitAddText = () => {
  addTextFormRef.value?.validate();
  addTextIsValid.value = addTextFormRef.value?.errors.length === 0;

  if (addTextIsValid.value) {
    for (const v of textModel.value.text.split(/\r?\n/)) {
      props.model.verses.push({
        id: null,
        groupId: null,
        verse: v,
        order: maxOrder.value + 1,
      });
    }
    textModel.value = {};

    calcChanges();
    emit('validated', 0, null, { changes: changes.value });
    addTextModal.value = false;
  }
};

const addVerse = () => {
  verse.value = {
    verse: '',
    linkVerses: [],
    index: props.model.verses.length,
    order: props.model.verses.length + 1,
  };
  search.value.search = '';
  linkableVerses.value = null;
  editVerseModal.value = true;
};

const editVerse = (index) => {
  setVerse(index).then(() => {
    search.value.search = verse.value.verse;
    linkableVerses.value = null;
    editVerseModal.value = true;
  });
};

const searchVerseLinks = () => {
  if (typeof parentOpenRequests.value === 'number') {
    parentOpenRequests.value++;
  }
  editVerseModal.value = false;

  let url = props.urls['verse_search'] + '?verse=' + encodeURIComponent(search.value.search);
  if (verse.value.id != null) {
    url += '&id=' + verse.value.id;
  }

  axios
      .get(url)
      .then((response) => {
        linkableVerses.value = response.data;
        editVerseModal.value = true;
        if (typeof parentOpenRequests.value === 'number') {
          parentOpenRequests.value--;
        }
      })
      .catch((error) => {
        console.log(error);
        alerts.value.push({
          type: 'error',
          message: 'Something went wrong while searching for linkable verses.',
          login: parentIsLoginError(error),
        });
        editVerseModal.value = true;
        if (typeof parentOpenRequests.value === 'number') {
          parentOpenRequests.value--;
        }
      });
};

const groupToggle = (action, groupId) => {
  switch (action) {
    case 'add':
      for (const linkableGroup of linkableVerses.value) {
        if (linkableGroup.group_id === groupId) {
          // data in elasticsearch result is sufficient
          if (linkableGroup.total == null || linkableGroup.total <= linkableGroup.group.length) {
            for (const linkableVerse of linkableGroup.group) {
              const linkVerse = JSON.parse(JSON.stringify(linkableVerse));
              linkVerse.groupId = linkVerse.group_id;
              delete linkVerse.group_id;
              verse.value.linkVerses.push(JSON.parse(JSON.stringify(linkVerse)));
            }
          }
          // data in elasticsearch result is insufficient, get from cache or db
          else {
            if (oldGroups.value[groupId] != null) {
              for (const linkableVerse of oldGroups.value[groupId]) {
                verse.value.linkVerses.push(JSON.parse(JSON.stringify(linkableVerse)));
              }
            } else {
              if (typeof parentOpenRequests.value === 'number') {
                parentOpenRequests.value++;
              }
              editVerseModal.value = false;
              axios
                  .get(props.urls['verse_variant_get'].replace('verse_variant_id', groupId))
                  .then((response) => {
                    oldGroups.value[groupId] = response.data;
                    for (const linkableVerse of oldGroups.value[groupId]) {
                      verse.value.linkVerses.push(JSON.parse(JSON.stringify(linkableVerse)));
                    }
                    editVerseModal.value = true;
                    if (typeof parentOpenRequests.value === 'number') {
                      parentOpenRequests.value--;
                    }
                  })
                  .catch((error) => {
                    console.log(error);
                    alerts.value.push({
                      type: 'error',
                      message: 'Something went wrong while getting for linked verses.',
                      login: parentIsLoginError(error),
                    });
                    editVerseModal.value = true;
                    if (typeof parentOpenRequests.value === 'number') {
                      parentOpenRequests.value--;
                    }
                  });
            }
          }
          break;
        }
      }
      break;
    case 'remove':
      for (let i = verse.value.linkVerses.length - 1; i >= 0; i--) {
        if (verse.value.linkVerses[i].groupId === groupId) {
          verse.value.linkVerses.splice(i, 1);
        }
      }
      break;
  }
};

const verseToggle = (action, id) => {
  switch (action) {
    case 'add':
      for (const linkableGroup of linkableVerses.value) {
        if (linkableGroup.group[0].id === id) {
          verse.value.linkVerses.push(JSON.parse(JSON.stringify(linkableGroup.group[0])));
          break;
        }
      }
      break;
    case 'remove':
      for (let i = verse.value.linkVerses.length - 1; i >= 0; i--) {
        if (verse.value.linkVerses[i].id === id) {
          verse.value.linkVerses.splice(i, 1);
        }
      }
      break;
  }
};

const updateText = () => {
  editVerseFormRef.value?.validate();
  isValid.value = editVerseFormRef.value?.errors.length === 0;

  if (isValid.value) {
    if (props.model.verses[verse.value.index] == null) {
      if (verse.value.linkVerses.length === 0) {
        delete verse.value.linkVerses;
      }
      // add new
      props.model.verses.push(JSON.parse(JSON.stringify(verse.value)));
    } else {
      // update
      props.model.verses[verse.value.index].verse = verse.value.verse;
    }

    calcChanges();
    emit('validated', 0, null, { changes: changes.value });
    editVerseModal.value = false;
  }
};

const updateTextRemoveLink = () => {
  editVerseFormRef.value?.validate();
  isValid.value = editVerseFormRef.value?.errors.length === 0;

  if (isValid.value) {
    // only update is possible
    props.model.verses[verse.value.index].verse = verse.value.verse;
    props.model.verses[verse.value.index].groupId = null;
    delete props.model.verses[verse.value.index].linkVerses;

    calcChanges();
    emit('validated', 0, null, { changes: changes.value });
    editVerseModal.value = false;
  }
};

const updateTextSetLinks = () => {
  editVerseFormRef.value?.validate();
  isValid.value = editVerseFormRef.value?.errors.length === 0;

  if (isValid.value) {
    if (props.model.verses[verse.value.index] == null) {
      // add new
      props.model.verses.push(JSON.parse(JSON.stringify(verse.value)));
    } else {
      // update
      props.model.verses[verse.value.index].verse = verse.value.verse;
      // remove linkVerses if same as original
      if (
          verse.value.groupId != null &&
          JSON.stringify(verse.value.linkVerses) === JSON.stringify(oldGroups.value[verse.value.groupId])
      ) {
        delete props.model.verses[verse.value.index].linkVerses;
      } else {
        props.model.verses[verse.value.index].linkVerses = JSON.parse(JSON.stringify(verse.value.linkVerses));
      }
    }

    calcChanges();
    emit('validated', 0, null, { changes: changes.value });
    editVerseModal.value = false;
  }
};

const delVerse = (index) => {
  verse.value = JSON.parse(JSON.stringify(props.model.verses[index]));
  verse.value.index = index;
  delVerseModal.value = true;
};

const submitDelVerse = () => {
  props.model.verses.splice(verse.value.index, 1);

  calcChanges();
  emit('validated', 0, null, { changes: changes.value });
  delVerseModal.value = false;
};

const onVerseOrderChange = () => {
  calcChanges();
  emit('validated', 0, null, { changes: changes.value });
};

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type);
  }
};

const disableFields = (disableKeys) => {
  disableFieldsHelper(props.keys, generalSchema.fields, disableKeys);
};

const enableFields = (enableKeys) => {
  enableFieldsHelper(props.keys, generalSchema.fields, props.values, enableKeys);
};

// Watchers
watch(
    () => props.model.numberOfVerses,
    (newValue) => {
      if (isNaN(newValue)) {
        props.model.numberOfVerses = null;
        nextTick(() => {
          validate();
        });
      }
    }
);

watch(
    () => verse.value?.verse,
    (newValue, oldValue) => {
      if (editVerseModal.value && search.value.search === oldValue) {
        search.value.search = newValue;
      }
    }
);

// Expose methods
defineExpose({
  validate,
  init,
  reload,
  enableFields,
  disableFields,
  changes,
  isValid,
});
</script>

<style scoped>
.draggable-item {
  cursor: move;
  margin-bottom: 10px;
}

.draggable-icon {
  margin-right: 10px;
  color: #999;
  cursor: move;
}

.pbottom-default {
  padding-bottom: 1rem;
}
</style>