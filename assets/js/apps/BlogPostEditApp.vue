<template>
  <div>
    <article class="col-sm-9 mbottom-large">
      <alert
          v-for="(item, index) in alerts"
          :key="index"
          :type="item.type"
          dismissible
          @dismissed="alerts.splice(index, 1)"
      >
        {{ item.message }}
      </alert>

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
      <div ref="anchorRef" />
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
import Vue from 'vue'
import axios from 'axios'

import { getErrorMessage, isLoginError } from "@/helpers/errorUtil"
import Reset from "@/Components/Edit/Modals/Reset.vue"
import Invalid from "@/Components/Edit/Modals/Invalid.vue"
import Save from "@/Components/Edit/Modals/Save.vue"
import Person from "@/Components/Edit/Panels/Person.vue";
import BasicBlogPost from "@/Components/Edit/Panels/BasicBlogPost.vue";
import Url from "@/Components/Edit/Panels/Url.vue";
import GeneralBibItem from "@/Components/Edit/Panels/GeneralBibItem.vue";
import Management from "@/Components/Edit/Panels/Management.vue";


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
  initIdentifiers: {
    type: String,
    default: '',
  },
  initRoles: {
    type: String,
    default: '',
  },
  initContributorRoles: {
    type: String,
    default: '',
  },
})

// Template refs
const personsRef = ref(null)
const basicRef = ref(null)
const urlsRef = ref(null)
const generalRef = ref(null)
const managementsRef = ref(null)
const anchorRef = ref(null)

// Reactive data
const roles = JSON.parse(props.initRoles)
const urls = JSON.parse(props.initUrls)
const data = JSON.parse(props.initData)

const blogPost = ref(null)
const modernPersons = ref([])
const blogs = ref([])
const managements = ref([])

const personRoles = {}
for (let role of roles) {
  // Make author a required field
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



const formOptions = reactive({
  validateAfterChanged: true,
  validationErrorClass: "has-error",
  validationSuccessClass: "success"
})

const openRequests = ref(0)
const alerts = ref([])
const saveAlerts = ref([])
const originalModel = ref({})
const diff = ref([])
const resetModal = ref(false)
const invalidModal = ref(false)
const saveModal = ref(false)
const invalidPanels = ref(false)
const scrollY = ref(null)
const isSticky = ref(false)
const stickyStyle = ref({})
const reloads = ref([])

const panelRefs = computed(() => ({
  persons: personsRef.value,
  basic: basicRef.value,
  urls: urlsRef.value,
  general: generalRef.value,
  managements: managementsRef.value,
}))

watch(scrollY, () => {
  if (anchorRef.value) {
    let anchor = anchorRef.value.getBoundingClientRect()
    if (anchor.top < 30) {
      isSticky.value = true
      stickyStyle.value = {
        width: anchor.width + 'px',
      }
    } else {
      isSticky.value = false
      stickyStyle.value = {}
    }
  }
})

const loadAsync = () => {
  reload('modernPersons',modernPersons.value)
  reload('blogs',blogs.value)
}

const setData = () => {
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
    axios.post(urls['blog_post_post'], toSave())
        .then((response) => {
          window.onbeforeunload = function () {}
          window.location = urls['blog_post_get'].replace('blog_post_id', response.data.id)
        })
        .catch((error) => {
          console.log(error)
          saveModal.value = true
          saveAlerts.value.push({
            type: 'error',
            message: 'Something went wrong while saving the blog post data.',
            extra: getErrorMessage(error),
            login: isLoginError(error)
          })
          openRequests.value--
        })
  } else {
    axios.put(urls['blog_post_put'], toSave())
        .then((response) => {
          window.onbeforeunload = function () {}
          window.location = urls['blog_post_get']
        })
        .catch((error) => {
          console.log(error)
          saveModal.value = true
          saveAlerts.value.push({
            type: 'error',
            message: 'Something went wrong while saving the blog post data.',
            extra: getErrorMessage(error),
            login: isLoginError(error)
          })
          openRequests.value--
        })
  }
}

const reload = (type,items) => {
  reloadSimpleItems(type,items)
}

const initScroll = () => {
  window.addEventListener('scroll', () => {
    scrollY.value = Math.round(window.scrollY)
  })
}

const validateForms = () => {
  for (let panel of panels) {
    const panelRef = panelRefs.value[panel]
    if (panelRef) {
      panelRef.validate()
    }
  }
}

const validated = (isValid, errors) => {
  invalidPanels.value = false
  for (let panel of panels) {
    const panelRef = panelRefs.value[panel]
    if (panelRef && !panelRef.isValid) {
      invalidPanels.value = true
      break
    }
  }
  calcDiff()
}

const calcDiff = () => {
  diff.value = []
  for (let panel of panels) {
    const panelRef = panelRefs.value[panel]
    if (panelRef) {
      diff.value = diff.value.concat(panelRef.changes)
    }
  }

  if (diff.value.length !== 0) {
    window.onbeforeunload = function(e) {
      let dialogText = 'There are unsaved changes.'
      e.returnValue = dialogText
      return dialogText
    }
  }
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

const reloadSimpleItems = (type, items) => {
  reloadItems(
      type,
      [type],
      [items], // Note: using eval here as in original, but consider a better approach
      urls[type.split(/(?=[A-Z])/).join('_').toLowerCase() + '_get'] // convert camel case to snake case
  )
}

const reloadItems = (type, keys, items, url, filters) => {
  console.log(type,items)
  // Be careful to mutate the existing array and not create a new one
  for (let panel of panels) {
    const panelRef = panelRefs.value[panel]
    if (panelRef) {
      panelRef.disableFields(keys)
    }
  }

  reloads.value.push(type)

  axios.get(url)
      .then((response) => {
        for (let i = 0; i < items.length; i++) {
          let data = []
          if (filters == null || filters[i] == null) {
            // Copy data
            data = response.data.filter(() => true)
          } else {
            data = response.data.filter(filters[i])
          }
          while (items[i].length) {
            items[i].splice(0, 1)
          }
          while (data.length) {
            items[i].push(data.shift())
          }
        }

        for (let panel of panels) {
          const panelRef = panelRefs.value[panel]
          if (panelRef) {
            panelRef.enableFields(keys)
          }
        }

        let typeIndex = reloads.value.indexOf(type)
        if (typeIndex > -1) {
          reloads.value.splice(typeIndex, 1)
        }
      })
      .catch((error) => {
        alerts.value.push({
          type: 'error',
          message: 'Something went wrong while loading data.',
          login: isLoginError(error)
        })
        console.log(error)
      })
}

onMounted(() => {
  blogPost.value = data.blogPost
  modernPersons.value = []
  blogs.value = []
  managements.value = data.managements

  initScroll()
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

  loadAsync()
})
</script>