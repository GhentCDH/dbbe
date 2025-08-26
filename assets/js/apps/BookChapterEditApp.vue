<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <Alerts
          v-for="(item, index) in alerts"
          :key="index"
          :type="item.type"
          dismissible
          @dismissed="alerts.splice(index, 1)"
      >
        {{ item.message }}
      </Alerts>

      <Person
          id="persons"
          ref="personsRef"
          header="Persons"
          :links="[{ title: 'Persons', reload: 'modernPersons', edit: urls['persons_search'] }]"
          :roles="roles"
          :model="model.personRoles"
          :values="modernPersons"
          :keys="{ modernPersons: { init: false } }"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <BasicBookChapter
          id="basic"
          ref="basicRef"
          header="Basic Information"
          :links="[{ title: 'Books', reload: 'books', edit: urls['bibliographies_search'] }]"
          :model="model.basic"
          :values="books"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <Url
          id="urls"
          ref="urlsRef"
          header="Urls"
          :model="model.urls"
          @validated="validated"
      />

      <Identification
          v-if="identifiers.length > 0"
          id="identification"
          ref="identificationRef"
          header="Identification"
          :identifiers="identifiers"
          :model="model.identification"
          @validated="validated"
      />

      <GeneralBibItem
          id="general"
          ref="generalRef"
          header="General"
          :model="model.general"
          @validated="validated"
      />

      <Management
          id="managements"
          ref="managementsRef"
          header="Management collections"
          :links="[{ title: 'Management collections', reload: 'managements', edit: urls['managements_edit'] }]"
          :model="model.managements"
          :values="managements"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
      />

      <btn id="actions" type="warning" :disabled="diff.length === 0" @click="resetModal = true">
        Reset
      </btn>
      <btn
          type="success"
          :disabled="diff.length === 0"
          @click="saveButton()"
      >
        {{ bookChapter ? 'Save changes' : 'Save' }}
      </btn>

      <div v-if="openRequests" class="loading-overlay">
        <div class="spinner" />
      </div>
    </article>

    <aside class="col-sm-3 inpage-nav-container xs-hide">
      <div ref="anchor" />
      <nav
          v-scrollspy
          role="navigation"
          class="padding-default bg-tertiary"
          :class="{ stick: isSticky }"
          :style="stickyStyle"
      >
        <h2>Quick navigation</h2>
        <ul class="linklist linklist-dark">
          <li><a href="#persons" :class="{'bg-danger': !(panelRefs.persons && panelRefs.persons.isValid)}">Persons</a></li>
          <li><a href="#basic" :class="{'bg-danger': !(panelRefs.basic && panelRefs.basic.isValid)}">Basic Information</a></li>
          <li><a href="#urls" :class="{'bg-danger': !(panelRefs.urls && panelRefs.urls.isValid)}">Urls</a></li>
          <li v-if="identifiers.length > 0">
            <a href="#identification" :class="{'bg-danger': !(panelRefs.identification && panelRefs.identification.isValid)}">Identification</a>
          </li>
          <li><a href="#general" :class="{'bg-danger': !(panelRefs.general && panelRefs.general.isValid)}">General</a></li>
          <li><a href="#managements" :class="{'bg-danger': !(panelRefs.managements && panelRefs.managements.isValid)}">Management collections</a></li>
          <li><a href="#actions">Actions</a></li>
        </ul>
      </nav>
    </aside>

    <Reset title="book chapter" :show="resetModal" @cancel="resetModal = false" @confirm="reset()" />
    <Invalid :show="invalidModal" @cancel="invalidModal = false" @confirm="invalidModal = false" />
    <Save
        title="book chapter"
        :show="saveModal"
        :diff="diff"
        :alerts="saveAlerts"
        @cancel="cancelSave()"
        @confirm="save()"
        @dismiss-alert="saveAlerts.splice($event, 1)"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'

import Alerts from '@/components/Alerts.vue'
import Reset from '@/components/Edit/Modals/Reset.vue'
import Invalid from '@/components/Edit/Modals/Invalid.vue'
import Save from '@/components/Edit/Modals/Save.vue'

import Person from '@/components/Edit/Panels/Person.vue'
import BasicBookChapter from '@/components/Edit/Panels/BasicBookChapter.vue'
import Url from '@/components/Edit/Panels/Url.vue'
import Identification from '@/components/Edit/Panels/Identification.vue'
import GeneralBibItem from '@/components/Edit/Panels/GeneralBibItem.vue'
import Management from '@/components/Edit/Panels/Management.vue'

import { useErrorAlert } from '@/composables/useErrorAlert'
import { usePanelValidation } from '@/composables/editAppComposables/usePanelValidation'
import { useModelDiff } from '@/composables/editAppComposables/useModelDiff'
import { useStickyNav } from '@/composables/editAppComposables/useStickyNav'
import { useSaveModel } from '@/composables/editAppComposables/useSaveModel'
import { disablePanels, enablePanels, updateItems } from '@/helpers/panelUtil'

const props = defineProps({
  initUrls: {
    type: String,
    default: '',
  },
  initData: {
    type: String,
    default: '',
  },
  initRoles: {
    type: String,
    default: '',
  },
  initIdentifiers: {
    type: String,
    default: '',
  },
})

const basicRef = ref(null)
const urlsRef = ref(null)
const generalRef = ref(null)
const managementsRef = ref(null)
const personsRef = ref(null)
const identificationRef = ref(null)
const anchor = ref(null)

const data = JSON.parse(props.initData)
const urls = JSON.parse(props.initUrls)
const roles = JSON.parse(props.initRoles)
const identifiers = JSON.parse(props.initIdentifiers)

const managements = ref(null)
const modernPersons = ref(null)

const personRoles = {}
for (let role of roles) {
  personRoles[role.systemName] = []
}

const model = reactive({
  basic: {
    title: null,
    book: null,
    startPage: null,
    endPage: null,
    rawPages: null,
  },
  urls: {},
  general: {},
  personRoles: {},
  identification: {},
  managements: {},
})

const panels = ['basic','urls','general','persons','identification','managements']

const alerts = ref([])
const handleError = useErrorAlert(alerts)
const bookChapter = ref(null)
const books = ref(null)

const reloads = ref([])
const resetModal = ref(false)
const invalidModal = ref(false)
const originalModel = ref({})

const panelRefs = computed(() => ({
  basic: basicRef.value,
  urls: urlsRef.value,
  general: generalRef.value,
  persons: personsRef.value,
  identification: identificationRef.value,
  managements: managementsRef.value,
}))


const { invalidPanels, validateForms, checkInvalidPanels } = usePanelValidation(panelRefs, panels)
const { diff, calcDiff } = useModelDiff(panelRefs, panels)
const { scrollY, isSticky, stickyStyle, initScrollListener } = useStickyNav(anchor)
const { saveModal, saveAlerts, openRequests, postUpdatedModel, putUpdatedModel } = useSaveModel(urls)

const setData = () => {
  bookChapter.value = data.bookChapter
  books.value = []
  managements.value = data.managements
  modernPersons.value = []
  identifiers.value = data.identifiers

  if (bookChapter.value != null) {
    model.basic = { ...bookChapter.value.basic }
    model.urls = { ...bookChapter.value.urls }
    model.general = { ...bookChapter.value.general }
    model.personRoles = { ...bookChapter.value.personRoles }
    model.identification = { ...bookChapter.value.identification }
    model.managements = { ...bookChapter.value.managements }
  }
  Object.assign(model.basic, {
    title: bookChapter.value.title,
    book: bookChapter.value.book,
    startPage: bookChapter.value.startPage,
    endPage: bookChapter.value.endPage,
    rawPages: bookChapter.value.rawPages,
  })

}

const toSave = () => {
  const result = {}
  for (const diffItem of diff.value) {
    if ('keyGroup' in diffItem) {
      result[diffItem.keyGroup] ||= {}
      result[diffItem.keyGroup][diffItem.key] = diffItem.value
    } else {
      result[diffItem.key] = diffItem.value
    }
  }
  return result
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (!bookChapter.value) {
    postUpdatedModel('book_chapter', toSave())
  } else {
    putUpdatedModel('book_chapter', toSave())
  }
}

const validated = () => {
  checkInvalidPanels()
  calcDiff(panelRefs, panels)
}

const reset = () => {
  resetModal.value = false
  Object.assign(model, JSON.parse(JSON.stringify(originalModel.value)))
  nextTick(() => validateForms())
}

const saveButton = () => {
  validateForms()
  if (invalidPanels.value) {
    invalidModal.value = true
  } else {
    saveModal.value = true
  }
}

const cancelSave = () => {
  saveModal.value = false
  saveAlerts.value = []
}

const reload = (type, items) => {
  const keys = [type]
  const url = urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get']
  reloadItems(type, keys, [items], url)
}

const reloadItems = (type, keys, items, url, filters) => {
  disablePanels(panelRefs, panels, keys)
  reloads.value.push(type)
  axios.get(url)
      .then(response => {
        updateItems(items, response.data, filters)
        enablePanels(panelRefs, panels, keys)
        reloads.value.splice(reloads.value.indexOf(type), 1)
      })
      .catch(handleError('Something went wrong while loading data.'))
}

onMounted(() => {
  initScrollListener()
  setData()
  originalModel.value = JSON.parse(JSON.stringify(model))

  nextTick(() => {
    if (!data.clone) {
      for (const panel of panels) {
        panelRefs.value[panel]?.init?.()
      }
    }
  })
  reload('modernPersons',modernPersons.value)
  reload('books',books.value)


})
</script>