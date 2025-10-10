<template>
  <panel :header="header">
    <div class="pbottom-large">
      <h3>Images</h3>
      <div v-bind="getRootProps()" class="dropzone-container">
        <input v-bind="getInputProps()" />
        <div class="dropzone-message">
          <i class="fa fa-upload"></i>
          <p v-if="isDragActive">Drop the files here...</p>
          <p v-else>Drag 'n' drop images here, or click to select files</p>
        </div>
      </div>
      <div class="row">
        <div
            v-for="(image, index) in model.images"
            :key="image.id"
            class="col-md-3"
        >
          <div
              class="thumbnail"
              :class="{
              'bg-warning': !image.public,
              'spinner-wrapper': !loadedImages.includes(image.id)
            }"
          >
            <div
                v-if="!loadedImages.includes(image.id)"
                class="spinner"
            />

            <a v-if="!erroredImages.includes(image.id)"
            :href="urls['image_get'].replace('image_id', image.id)"
            data-type="image"
            data-gallery="gallery"
            data-toggle="lightbox"
            :data-title="image.filename"
            >
            <img
                v-if="pageLoaded"
                :src="urls['image_get'].replace('image_id', image.id)"
                :alt="image.filename"
                @load="imageLoaded(image.id)"
                @error="imageErrored(image.id)"
            >
            </a>
            <span
                v-else
                class="text-danger"
            >
              <i class="fa fa-exclamation-circle" />
              {{ image.filename }}
            </span>

            <a v-if="loadedImages.includes(image.id)"
            class="image-public"
            @click.prevent="toggleImagePublic(index)"
            >
            <i
                v-if="image.public"
                class="fa fa-users"
            />
            <i
                v-else
                class="fa fa-user"
            />
            </a>

            <a v-if="loadedImages.includes(image.id)"
            class="image-delete"
            @click.prevent="delImage(index)"
            >
            <i class="fa fa-trash-o" />
            </a>
          </div>
        </div>
      </div>
    </div>

    <div>
      <h3>Image links</h3>
      <table
          v-if="model.imageLinks && model.imageLinks.length > 0"
          class="table table-striped table-bordered table-hover"
      >
        <thead>
        <tr>
          <th>Link</th>
          <th>Public</th>
          <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <tr
            v-for="(imageLink, index) in model.imageLinks"
            :key="index"
        >
          <td>{{ imageLink.url }}</td>
          <td>
            <i
                v-if="imageLink.public"
                class="fa fa-check"
            />
            <i
                v-else
                class="fa fa-times"
            />
          </td>
          <td>
            <a href="#"
            title="Edit"
            class="action"
            @click.prevent="updateLink(imageLink, index)"
            >
            <i class="fa fa-pencil-square-o" />
            </a>

            <a href="#"
            title="Delete"
            class="action"
            @click.prevent="delLink(index)"
            >
            <i class="fa fa-trash-o" />
            </a>
          </td>
        </tr>
        </tbody>
      </table>
      <Btn @click.native="newLink">
        <i class="fa fa-plus" />&nbsp;Add a new image link
      </Btn>
    </div>

    <!-- Public Image Modal -->
    <Modal
        :model-value="publicImageModal"
        title="Edit image public state"
        auto-focus
    >
      <Alerts type="alert">
        <p>This will modify the public state of this image in all occurrences. Do you wish to continue?</p>
      </Alerts>
      <template #footer>
        <Btn @click.native="publicImageModal = false">
          Cancel
        </Btn>
        <Btn
            :disabled="submitToggleImagePublicDisabled"
            type="alert"
            @click.native="submitToggleImagePublic"
        >
          Update
        </Btn>
      </template>
    </Modal>

    <!-- Update Link Modal -->
    <Modal
        :model-value="updateLinkModal"
        title="Edit image link"
        size="lg"
        auto-focus
    >
      <alert type="warning">
        <p>This will modify the url or public state in image links with this url in all occurrences.</p>
        <p>If you don't want this to happen, create a new image link with a different url.</p>
      </alert>
      <vue-form-generator
          ref="editFormRef"
          :schema="editSchema"
          :model="editLink"
          :options="formOptions"
          @validated="validated"
      />
      <template #footer>
        <Btn @click.native="cancelUpdateLink">
          Cancel
        </Btn>
        <Btn
            :disabled="submitUpdateLinkDisabled"
            type="alert"
            @click.native="submitUpdateLink"
        >
          {{ linkIndex > -1 ? 'Update' : 'Add' }}
        </Btn>
      </template>
    </Modal>

    <!-- Delete Image Modal -->
    <Modal
        :model-value="delImageModal"
        title="Delete image"
        auto-focus
    >
      <p>Are you sure you want to delete this image?</p>
      <template #footer>
        <Btn @click.native="delImageModal = false">
          Cancel
        </Btn>
        <Btn
            :disabled="submitDeleteImageDisabled"
            type="danger"
            @click.native="submitDeleteImage"
        >
          Delete
        </Btn>
      </template>
    </Modal>

    <!-- Delete Link Modal -->
    <Modal
        :model-value="delLinkModal"
        title="Delete image link"
        auto-focus
    >
      <p>Are you sure you want to delete this image link?</p>
      <template #footer>
        <Btn @click.native="delLinkModal = false">
          Cancel
        </Btn>
        <Btn
            :disabled="submitDeleteLinkDisabled"
            type="danger"
            @click.native="submitDeleteLink"
        >
          Delete
        </Btn>
      </template>
    </Modal>
  </panel>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useDropzone } from 'vue3-dropzone';
import Panel from '../Panel.vue';
import { disableFields, enableFields } from '@/helpers/formFieldUtils';
import validatorUtil from '@/helpers/validatorUtil';
import axios from 'axios';
import Alerts from "@/components/Alerts.vue";
import { Btn, Modal } from 'uiv';

const props = defineProps({
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
  urls: {
    type: Object,
    default: () => ({}),
  },
  keys: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['validated', 'reload']);

// Refs
const editFormRef = ref(null);
const pageLoaded = ref(false);
const loadedImages = ref([]);
const erroredImages = ref([]);
const publicImageModal = ref(false);
const updateLinkModal = ref(false);
const delImageModal = ref(false);
const delLinkModal = ref(false);
const imageIndex = ref(null);
const linkIndex = ref(null);
const submitToggleImagePublicDisabled = ref(false);
const submitUpdateLinkDisabled = ref(false);
const submitDeleteImageDisabled = ref(false);
const submitDeleteLinkDisabled = ref(false);
const internalChanges = ref([]);
const isValid = ref(true);
const originalModel = ref({});

// Edit link model
const editLink = ref({});

// Dropzone handler
const onDrop = async (acceptedFiles) => {
  if (!acceptedFiles || acceptedFiles.length === 0) return;

  for (const file of acceptedFiles) {
    try {
      const formData = new FormData();
      formData.append('file', file);

      const response = await axios.post(props.urls['image_post'], formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      fileAdded(response.data);
    } catch (error) {
      console.error('Upload failed:', error);
    }
  }
};

// useDropzone composable
const { getRootProps, getInputProps, isDragActive } = useDropzone({
  onDrop,
  accept: {
    'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg']
  },
  maxSize: 10485760, // 10MB in bytes
  multiple: true,
});

// Edit schema
const editSchema = {
  fields: {
    url: {
      type: 'input',
      inputType: 'url',
      label: 'Url',
      labelClasses: 'control-label',
      model: 'url',
      required: true,
      validator: validatorUtil.regexp,
      pattern: '^https?:\\/\\/(www\\.)?.*$',
    },
    public: {
      type: 'checkbox',
      styleClasses: 'has-warning',
      label: 'Public',
      labelClasses: 'control-label',
      model: 'public',
    },
  },
};

// Form options
const formOptions = {
  validateAfterChanged: true,
  validationErrorClass: 'has-error',
  validationSuccessClass: 'success',
};

// Computed
const changes = computed(() => internalChanges.value);

// Methods
const validate = () => {};

const calcChanges = () => {
  internalChanges.value = [];

  // Images
  if (
      JSON.stringify(props.model.images) !== JSON.stringify(originalModel.value.images) &&
      !(props.model.images == null && originalModel.value.images == null)
  ) {
    internalChanges.value.push({
      key: 'images',
      label: 'Images',
      old: displayImages(originalModel.value.images),
      new: displayImages(props.model.images),
      value: props.model.images,
    });
  }

  // Image links
  if (
      JSON.stringify(props.model.imageLinks) !== JSON.stringify(originalModel.value.imageLinks) &&
      !(props.model.imageLinks == null && originalModel.value.imageLinks == null)
  ) {
    internalChanges.value.push({
      key: 'imageLinks',
      label: 'Image links',
      old: displayLinks(originalModel.value.imageLinks),
      new: displayLinks(props.model.imageLinks),
      value: props.model.imageLinks,
    });
  }
};

const toggleImagePublic = (index) => {
  imageIndex.value = index;
  publicImageModal.value = true;
};

const updateLink = (link, index) => {
  linkIndex.value = index;
  editLink.value = JSON.parse(JSON.stringify(link));
  updateLinkModal.value = true;
};

const newLink = () => {
  linkIndex.value = -1;
  editLink.value = {
    url: '',
    public: true,
  };
  updateLinkModal.value = true;
};

const delImage = (index) => {
  imageIndex.value = index;
  delImageModal.value = true;
};

const delLink = (index) => {
  linkIndex.value = index;
  delLinkModal.value = true;
};

const validated = (valid, errors) => {
  isValid.value = valid;
};

const submitToggleImagePublic = () => {
  submitToggleImagePublicDisabled.value = true;
  setTimeout(() => {
    submitToggleImagePublicDisabled.value = false;
  }, 1000);

  props.model.images[imageIndex.value].public = !props.model.images[imageIndex.value].public;
  calcChanges();
  emit('validated', 0, null, { changes: changes.value });
  publicImageModal.value = false;
};

const cancelUpdateLink = () => {
  updateLinkModal.value = false;
  isValid.value = true;
};

const submitUpdateLink = () => {
  submitUpdateLinkDisabled.value = true;
  setTimeout(() => {
    submitUpdateLinkDisabled.value = false;
  }, 1000);

  editFormRef.value?.validate();

  if (editFormRef.value?.errors.length === 0) {
    if (linkIndex.value > -1) {
      // Update existing
      props.model.imageLinks[linkIndex.value] = JSON.parse(JSON.stringify(editLink.value));
    } else {
      // Add new
      props.model.imageLinks.push(JSON.parse(JSON.stringify(editLink.value)));
    }
    calcChanges();
    emit('validated', 0, null, { changes: changes.value });
    updateLinkModal.value = false;
  }
};

const submitDeleteImage = () => {
  submitDeleteImageDisabled.value = true;
  setTimeout(() => {
    submitDeleteImageDisabled.value = false;
  }, 1000);

  props.model.images.splice(imageIndex.value, 1);
  calcChanges();
  emit('validated', 0, null, { changes: changes.value });
  delImageModal.value = false;
};

const submitDeleteLink = () => {
  submitDeleteLinkDisabled.value = true;
  setTimeout(() => {
    submitDeleteLinkDisabled.value = false;
  }, 1000);

  props.model.imageLinks.splice(linkIndex.value, 1);
  calcChanges();
  emit('validated', 0, null, { changes: changes.value });
  delLinkModal.value = false;
};

const fileAdded = (response) => {
  props.model.images.push(response);
  calcChanges();
  emit('validated', 0, null, { changes: changes.value });
};

const displayImages = (images) => {
  if (images == null) {
    return [];
  }
  return images.map(image =>
      `${image.filename} (${image.public ? 'Public' : 'Not public'})`
  );
};

const displayLinks = (links) => {
  if (links == null) {
    return null;
  }
  return links.map(link =>
      `${link.url} (${link.public ? 'Public' : 'Not public'})`
  );
};

const imageLoaded = (id) => {
  loadedImages.value.push(id);
};

const imageErrored = (id) => {
  loadedImages.value.push(id);
  erroredImages.value.push(id);
};

const init = () => {
  originalModel.value = JSON.parse(JSON.stringify(props.model));
};

const reload = (type) => {
  if (!props.reloads.includes(type)) {
    emit('reload', type);
  }
};

const disableFieldsMethod = (disableKeys) => {
  disableFields(props.keys, props.fields, disableKeys);
};

const enableFieldsMethod = (enableKeys) => {
  enableFields(props.keys, props.fields, props.values, enableKeys);
};

// Lifecycle
onMounted(() => {
  window.addEventListener('load', () => {
    pageLoaded.value = true;
  });
});

// Expose methods for parent component access
defineExpose({
  validate,
  calcChanges,
  init,
  reload,
  disableFields: disableFieldsMethod,
  enableFields: enableFieldsMethod,
  changes,
});
</script>

<style scoped>
.dropzone-container {
  border: 2px dashed #0087F7;
  border-radius: 5px;
  background: white;
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  min-height: 150px;
  transition: all 0.3s ease;
  margin-bottom: 20px;
}

.dropzone-container:hover {
  background: #f8f9fa;
  border-color: #0056b3;
}

.dropzone-message {
  pointer-events: none;
}

.dropzone-message i {
  font-size: 48px;
  color: #0087F7;
  display: block;
  margin-bottom: 15px;
}

.dropzone-message p {
  font-size: 16px;
  color: #666;
  margin: 0;
}
</style>