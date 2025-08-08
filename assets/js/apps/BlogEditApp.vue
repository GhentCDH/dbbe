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
      <BasicBlog
          id="basic"
          ref="basicRef"
          header="Basic Information"
          :model="model.basic"
          @validated="validated"
      />
      <Url
          id="urls"
          ref="urlsRef"
          header="Additional urls"
          :model="model.urls"
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
          v-if="blog"
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
        title="blog"
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
        title="blog"
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
import { ref, reactive, onMounted, nextTick, watch,computed} from 'vue'
import axios from 'axios'
import { getErrorMessage, isLoginError } from "@/helpers/errorUtil"
import Reset from "@/Components/Edit/Modals/Reset.vue"
import Invalid from "@/Components/Edit/Modals/Invalid.vue"
import Save from "@/Components/Edit/Modals/Save.vue"
import BasicBlog from "@/Components/Edit/Panels/BasicBlog.vue";
import Url from "@/Components/Edit/Panels/Url.vue";
import GeneralBibItem from "@/Components/Edit/Panels/GeneralBibItem.vue";
import Alerts from "@/Components/Alerts.vue";
import Management from "@/Components/Edit/Panels/Management.vue";
import {disablePanels, enablePanels, updateItems} from "@/helpers/panelUtil";
import {useErrorAlert} from "@/composables/useErrorAlert";
import {usePanelValidation} from "@/composables/usePanelValidation";
import {useModelDiff} from "@/composables/useModelDiff";
import {useStickyNav} from "@/composables/useStickyNav";
import {useSaveModel} from "@/composables/useSaveModel";

// Props
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
  }
})

const basicRef=ref(null)
const urlsRef=ref(null)
const generalRef=ref(null)
const managementsRef=ref(null)

const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)

const blog = ref(null)
const managements = ref(null)

const model = reactive({
  basic: {
    url: null,
    title: null,
    lastAccessed: null,
  },
  urls: { urls: [] },
  managements: {
    managements: [],
  },
})

const panels = [
  'basic',
  'urls',
  'general',
  'managements',
]

const alerts = ref([])
const originalModel = ref({})
const resetModal = ref(false)
const invalidModal = ref(false)
const reloads = ref([])
const anchor = ref(null)
const handleError = useErrorAlert(alerts)

const panelRefs = computed(() => ({
  basic: basicRef.value,
  urls: urlsRef.value,
  general: generalRef.value,
  managements: managementsRef.value,
}))

const {
  invalidPanels,
  validateForms,
  checkInvalidPanels,
} = usePanelValidation(panelRefs, panels)

const {
  diff,
  calcDiff,
} = useModelDiff(panelRefs, panels)

const {
  scrollY,
  isSticky,
  stickyStyle,
  initScrollListener,
} = useStickyNav(anchor)

const {
  saveModal,
  saveAlerts,
  openRequests,
  postUpdatedModel,
  putUpdatedModel
} = useSaveModel(urls)


const setData = () => {
  blog.value = data.blog
  managements.value = data.managements
  if (blog.value != null) {
    model.basic = {
      url: blog.value.url,
      title: blog.value.title,
      lastAccessed: blog.value.lastAccessed,
    }

    model.urls = {
      urls: blog.value.urls == null ? null : blog.value.urls.map(
          function(url, index) {
            url.tgIndex = index + 1
            return url
          }
      )
    }

    model.general = {
      publicComment: blog.value.publicComment,
      privateComment: blog.value.privateComment,
    }

    model.managements = {
      managements: blog.value.managements,
    }
  }
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (blog.value == null) {
    postUpdatedModel('blog',toSave());
  } else {
    putUpdatedModel('blog',toSave());

  }
}

const validated = (isValid, errors) => {
  checkInvalidPanels()
  calcDiff(panelRefs, panels)
}

const toSave = () => {
  let result = {}
  for (let diffItem of diff.value) {
    if ('keyGroup' in diffItem) {
      if (!(diffItem.keyGroup in result)) {
        result[diffItem.keyGroup] = {}
      }
      result[diffItem.keyGroup][diffItem.key] = diffItem.value
    } else {
      result[diffItem.key] = diffItem.value
    }
  }
  return result
}

const reset = () => {
  resetModal.value = false
  Object.assign(model, JSON.parse(JSON.stringify(originalModel.value)))
  nextTick(() => {
    validateForms()
  })
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

const reload = (type) => {
  reloadItems(
      type,
      [type],
      [data[type]],
      urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
  )
}
const reloadItems = (type, keys, items, url, filters) => {
  disablePanels(panelRefs, panels,keys)
  reloads.value.push(type)
  axios.get(url)
      .then((response) => {
        updateItems(items, response.data, filters)
        enablePanels(panelRefs, panels,keys)
        let typeIndex = reloads.value.indexOf(type)
        if (typeIndex > -1) {
          reloads.value.splice(typeIndex, 1)
        }
      })
      .catch(handleError('Something went wrong while loading data.'))
}

onMounted(() => {
  initScrollListener()
  setData()
  originalModel.value = JSON.parse(JSON.stringify(model))

  nextTick(() => {
    if (!data.clone) {
      for (let panel of panels) {
        const panelRef = panelRefs.value[panel]
        if (panelRef) {
          panelRef.init()
        }
      }
    }
  })
})

</script>