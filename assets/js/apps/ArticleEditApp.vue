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
                :links="[{title: 'Persons', reload: 'modernPersons', edit: urls['persons_search']}]"
                :roles="roles"
                :model="model.personRoles"
                :values="modernPersons"
                :keys="{modernPersons: {init: false}}"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <BasicArticle
                id="basic"
                ref="basicRef"
                header="Basic Information"
                :links="[{title: 'Journals', reload: 'journals', edit: urls['journals_edit']}, {title: 'Journal issues', reload: 'journalIssues', edit: urls['journal_issues_edit']}]"
                :model="model.basic"
                :values="journalsAndIssues"
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
                :links="[{title: 'Management collections', reload: 'managements', edit: urls['managements_edit']}]"
                :model="model.managements"
                :values="managements"
                :reloads="reloads"
                @validated="validated"
                @reload="reload"
            />

            <btn
                id="actions"
                type="warning"
                :disabled="diff.length === 0"
                @click="resetModal=true"
            >
                Reset
            </btn>
            <btn
                v-if="article"
                type="success"
                :disabled="(diff.length === 0)"
                @click="saveButton()"
            >
                Save changes
            </btn>
            <btn
                v-else
                type="success"
                :disabled="(diff.length === 0)"
                @click="saveButton()"
            >
                Save
            </btn>
            <div
                v-if="openRequests"
                class="loading-overlay"
            >
                <div class="spinner" />
            </div>
        </article>
        <aside class="col-sm-3 inpage-nav-container xs-hide">
            <div ref="anchor" />
            <nav
                v-scrollspy
                role="navigation"
                class="padding-default bg-tertiary"
                :class="{stick: isSticky}"
                :style="stickyStyle"
            >
                <h2>Quick navigation</h2>
                <ul class="linklist linklist-dark">
                    <li>
                        <a
                            href="#persons"
                            :class="{'bg-danger': !(panelRefs.persons && panelRefs.persons.isValid)}"
                        >Persons</a>
                    </li>
                    <li>
                        <a
                            href="#basic"
                            :class="{'bg-danger': !(panelRefs.basic && panelRefs.basic.isValid)}"
                        >Basic information</a>
                    </li>
                    <li>
                        <a
                            href="#urls"
                            :class="{'bg-danger': !(panelRefs.urls && panelRefs.urls.isValid)}"
                        >Urls</a>
                    </li>
                    <li v-if="identifiers.length > 0">
                        <a
                            href="#identification"
                            :class="{'bg-danger': !(panelRefs.identification && panelRefs.identification.isValid)}"
                        >Identification</a>
                    </li>
                    <li>
                        <a
                            href="#general"
                            :class="{'bg-danger': !(panelRefs.general && panelRefs.general.isValid)}"
                        >General</a>
                    </li>
                    <li>
                        <a
                            href="#managements"
                            :class="{'bg-danger': !(panelRefs.managements && panelRefs.managements.isValid)}"
                        >Management collections</a>
                    </li>
                    <li><a href="#actions">Actions</a></li>
                </ul>
            </nav>
        </aside>
        <Reset
            title="article"
            :show="resetModal"
            @cancel="resetModal=false"
            @confirm="reset()"
        />
        <Invalid
            :show="invalidModal"
            @cancel="invalidModal=false"
            @confirm="invalidModal=false"
        />
        <Save
            title="article"
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

import Reset from '@/components/Edit/Modals/Reset.vue'
import Invalid from '@/components/Edit/Modals/Invalid.vue'
import Save from '@/components/Edit/Modals/Save.vue'

import { getErrorMessage, isLoginError } from '@/helpers/errorUtil'
import { disablePanels, enablePanels, updateItems } from '@/helpers/panelUtil'
import { usePanelValidation } from '@/composables/editAppComposables/usePanelValidation'
import { useModelDiff } from '@/composables/editAppComposables/useModelDiff'
import { useStickyNav } from '@/composables/editAppComposables/useStickyNav'
import Person from "@/components/Edit/Panels/Person.vue";
import BasicArticle from "@/components/Edit/Panels/BasicArticle.vue";
import Url from "@/components/Edit/Panels/Url.vue";
import Identification from "@/components/Edit/Panels/Identification.vue";
import GeneralBibItem from "@/components/Edit/Panels/GeneralBibItem.vue";
import Management from "@/components/Edit/Panels/Management.vue";
import Alerts from "@/components/Alerts.vue";
import {useSaveModel} from "@/composables/editAppComposables/useSaveModel";
import {useErrorAlert} from "@/composables/useErrorAlert";

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

const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)
const identifiers = JSON.parse(props.initIdentifiers)
const roles = JSON.parse(props.initRoles)

const managements = ref(null)
const modernPersons = ref(null)

const personRoles = {}
for (let role of roles) {
  personRoles[role.systemName] = []
}
const model = reactive({
  personRoles: personRoles,
  basic: {
    title: null,
    journal: null,
    journalIssue: null,
    startPage: null,
    endPage: null,
    rawPages: null,
  },
  urls: { urls: [] },
  identification: {},
  managements: {},
  general: {},
})

const panels = ['persons', 'basic', 'urls', 'general', 'managements']

const alerts = ref([])
const article = ref(null)
const journals = ref([])
const journalsAndIssues = reactive({ journals: [], journalIssues: [] })

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


for (const identifier of identifiers) {
  model.identification[identifier.systemName] = null
}

if (identifiers.length > 0) {
  panels.push('identification')
}
//
// for (const role of roles) {
//   model.personRoles[role.systemName] = []
// }


const { invalidPanels, validateForms, checkInvalidPanels } = usePanelValidation(panelRefs, panels)
const { diff, calcDiff } = useModelDiff(panelRefs, panels)
const { saveModal, saveAlerts, openRequests, postUpdatedModel, putUpdatedModel } = useSaveModel(urls)
const { scrollY, isSticky, stickyStyle, initScrollListener } = useStickyNav(anchor)

const setData = () => {
  article.value = data.article
  modernPersons.value = []
  journalsAndIssues.journals = []
  journalsAndIssues.journalIssues = []
  managements.value = data.managements

  if (article.value !== null) {

    for (let role of roles) {
      model.personRoles[role.systemName] = article.value.personRoles == null ? [] : article.value.personRoles[role.systemName]
    }

    Object.assign(model.basic, {
      title: article.value.title,
      journal: article.value.journal,
      journalIssue: article.value.journalIssue,
      startPage: article.value.startPage,
      endPage: article.value.endPage,
      rawPages: article.value.rawPages,
    })

    model.urls.urls = article.value.urls?.map((url, index) => {
      url.tgIndex = index + 1
      return url
    }) ?? []

    for (const identifier of identifiers) {
      model.identification[identifier.systemName] = article.value.identifications?.[identifier.systemName] ?? []
    }

    model.general = {
      publicComment: article.value.publicComment,
      privateComment: article.value.privateComment,
    }

    model.managements.managements = article.value.managements
  }
}

const toSave = () => {
  const result = {}
  for (const change of diff.value) {
    if ('keyGroup' in change) {
      result[change.keyGroup] ||= {}
      result[change.keyGroup][change.key] = change.value
    } else {
      result[change.key] = change.value
    }
  }
  return result
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (article.value == null) {
    postUpdatedModel('article',toSave());
  } else {
    putUpdatedModel('article',toSave());
  }
}

const validated = () => {
  checkInvalidPanels()
  calcDiff()
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

const reload = (type,items) => {
  if (type === 'journals' || type === 'journalIssues') {
    reloadNestedItems(type, journalsAndIssues)
  } else {
    reloadSimpleItems(type,items)
  }
}

const reloadSimpleItems = (type,items) => {
  const url = urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get']
  reloadItems(type, [type], [items], url)
}

const reloadNestedItems = (type, parent) => {
  const items = Array.isArray(parent) ? parent.map(p => p[type]) : [parent[type]]
  const url = urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get']
  reloadItems(type, [type], items, url)
}

const reloadItems = (type, keys, items, url, filters = null) => {
  disablePanels(panelRefs, panels, keys)
  reloads.value.push(type)
  axios.get(url)
      .then(response => {
        updateItems(items, response.data, filters)
        enablePanels(panelRefs, panels, keys)
        reloads.value.splice(reloads.value.indexOf(type), 1)
      })
      .catch(error => {
        alerts.value.push({ type: 'error', message: 'Something went wrong while loading data.', login: isLoginError(error) })
        console.log(error)
      })
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
  reload('journals',journals.value)
  reload('journalIssues',journalsAndIssues.value)
})
</script>
