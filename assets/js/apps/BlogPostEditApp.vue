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

      <BasicBlogPost
          id="basic"
          ref="basicRef"
          header="Basic Information"
          :links="[{title: 'Blogs', reload: 'blogs', edit: urls['bibliographies_search_blog']}]"
          :model="model.basic"
          :values="blogs"
          :reloads="reloads"
          @validated="validated"
          @reload="reload"
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
          @click="resetModal = true"
      >
        Reset
      </btn>
      <btn
          v-if="blogPost"
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
                :class="{'bg-danger': !(personsRef && personsRef.isValid)}"
            >Persons</a>
          </li>
          <li>
            <a
                href="#basic"
                :class="{'bg-danger': !(basicRef && basicRef.isValid)}"
            >Basic information</a>
          </li>
          <li>
            <a
                href="#urls"
                :class="{'bg-danger': !(urlsRef && urlsRef.isValid)}"
            >Urls</a>
          </li>
          <li>
            <a
                href="#general"
                :class="{'bg-danger': !(generalRef && generalRef.isValid)}"
            >General</a>
          </li>
          <li>
            <a
                href="#managements"
                :class="{'bg-danger': !(managementsRef && managementsRef.isValid)}"
            >Management collections</a>
          </li>
          <li><a href="#actions">Actions</a></li>
        </ul>
      </nav>
    </aside>
    <Reset
        title="blog post"
        :show="resetModal"
        @cancel="resetModal = false"
        @confirm="reset()"
    />
    <Invalid
        :show="invalidModal"
        @cancel="invalidModal = false"
        @confirm="invalidModal = false"
    />
    <Save
        title="blog post"
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
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'
import Reset from "@/components/Edit/Modals/Reset.vue"
import Invalid from "@/components/Edit/Modals/Invalid.vue"
import Save from "@/components/Edit/Modals/Save.vue"
import Person from "@/components/Edit/Panels/Person.vue";
import BasicBlogPost from "@/components/Edit/Panels/BasicBlogPost.vue";
import Url from "@/components/Edit/Panels/Url.vue";
import GeneralBibItem from "@/components/Edit/Panels/GeneralBibItem.vue";
import Management from "@/components/Edit/Panels/Management.vue";
import Alerts from "@/components/Alerts.vue";
import {disablePanels, enablePanels, updateItems} from "@/helpers/panelUtil";
import {useErrorAlert} from "@/composables/useErrorAlert";
import {usePanelValidation} from "@/composables/editAppComposables/usePanelValidation";
import {useModelDiff} from "@/composables/editAppComposables/useModelDiff";
import {useStickyNav} from "@/composables/editAppComposables/useStickyNav";
import {useSaveModel} from "@/composables/editAppComposables/useSaveModel";

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
})

const personsRef = ref(null)
const basicRef = ref(null)
const urlsRef = ref(null)
const generalRef = ref(null)
const managementsRef = ref(null)
const anchor = ref(null)

const roles = JSON.parse(props.initRoles)
const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)

const blogPost = ref(null)
const modernPersons = ref([])
const blogs = ref([])
const managements = ref([])

const personRoles = {}
for (let role of roles) {
  if (role.systemName === 'author') {
    role.required = true
  }
  personRoles[role.systemName] = []
}
const model = reactive({
  personRoles: personRoles,
  basic: {
    blog: null,
    url: null,
    title: null,
    postDate: null,
  },
  urls: { urls: [] },
  managements: {
    managements: [],
  },
})

const panels = ['persons', 'basic', 'urls', 'general', 'managements']

const alerts = ref([])
const originalModel = ref({})
const resetModal = ref(false)
const invalidModal = ref(false)
const reloads = ref([])
const handleError = useErrorAlert(alerts)

const panelRefs = computed(() => ({
  persons: personsRef.value,
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
  blogPost.value = data.blogPost
  modernPersons.value = []
  blogs.value = []
  managements.value = data.managements
  if (blogPost.value != null) {
    for (let role of roles) {
      model.personRoles[role.systemName] = blogPost.value.personRoles == null ? [] : blogPost.value.personRoles[role.systemName]
    }
    model.basic = {
      blog: blogPost.value.blog,
      url: blogPost.value.url,
      title: blogPost.value.title,
      postDate: blogPost.value.postDate,
    }
    model.urls = {
      urls: blogPost.value.urls == null ? null : blogPost.value.urls.map(
          function(url, index) {
            url.tgIndex = index + 1
            return url
          }
      )
    }
    model.general = {
      publicComment: blogPost.value.publicComment,
      privateComment: blogPost.value.privateComment,
    }
    model.managements = {
      managements: blogPost.value.managements,
    }
  }
}

const save = () => {
  openRequests.value++
  saveModal.value = false
  if (blogPost.value == null) {
    postUpdatedModel('blog_post',toSave());
  } else {
    putUpdatedModel('blog_post',toSave());
  }
}

const validated = (isValid, errors) => {
  checkInvalidPanels()
  calcDiff()
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

const reload = (type, items) => {
  reloadItems(
      type,
      [type],
      [items], // Note: using eval here as in original, but consider a better approach
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
  reload('modernPersons',modernPersons.value)
  reload('blogs',blogs.value)
})
</script>