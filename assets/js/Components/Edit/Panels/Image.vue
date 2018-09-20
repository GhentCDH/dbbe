<template>
    <panel :header="header">
        <div class="pbottom-large">
            <h3>Images</h3>
            <vue-dropzone
                id="dropzone"
                ref="dropzone"
                :options="dropzoneOptions"
                :duplicate-check="true"
                @vdropzone-success="fileAdded"
            />
            <div class="row">
                <div
                    v-for="(image, index) in model.images"
                    :key="image.id"
                    class="col-md-3"
                >
                    <div
                        class="thumbnail"
                        :class="{'bg-warning' : !(image.public)}"
                    >
                        <a
                            :href="urls['image_get'].replace('image_id', image.id)"
                            data-type="image"
                            data-gallery="gallery"
                            data-toggle="lightbox"
                            :data-title="image.filename"
                        >
                            <img
                                :src="urls['image_get'].replace('image_id', image.id)"
                                :alt="image.filename"
                            >
                        </a>
                        <a
                            class="image-delete"
                            @click.prevent="delImage(index)"
                        >
                            <i class="fa fa-trash-o" />
                        </a>
                        <a
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
                    </div>
                </div>
            </div>
        </div>
        <modal
            v-model="delImageModal"
            title="Delete image"
            auto-focus
        >
            <p>Are you sure you want to delete this image?</p>
            <div slot="footer">
                <btn @click="delImageModal=false">Cancel</btn>
                <btn
                    type="danger"
                    @click="submitDeleteImage()"
                >
                    Delete
                </btn>
            </div>
        </modal>
    </panel>
</template>
<script>
import Vue from 'vue'
import VueFormGenerator from 'vue-form-generator'
import vue2Dropzone from 'vue2-dropzone'

import AbstractPanelForm from '../AbstractPanelForm'
import AbstractField from '../../FormFields/AbstractField'
import Panel from '../Panel'

Vue.use(VueFormGenerator)
Vue.component('panel', Panel)
Vue.component('vueDropzone', vue2Dropzone)

var $ = require('jquery')
require('bootstrap-sass')
require('ekko-lightbox')

export default {
    mixins: [
        AbstractField,
        AbstractPanelForm,
    ],
    props: {
        urls: {
            type: Object,
            default: () => {return {}}
        },
    },
    data() {
        return {
            delImageModal: false,
            imageIndex: null,
            dropzoneOptions: {
                url: this.urls['image_post'],
                maxFilesize: 10,
                dictDefaultMessage: "<i class='fa fa-upload'></i> Upload images",
            },
        }
    },
    methods: {
        init() {
            this.originalModel = JSON.parse(JSON.stringify(this.model))
        },
        validate() {},
        calcChanges() {
            this.changes = []
            // images
            if (
                JSON.stringify(this.model.images) !== JSON.stringify(this.originalModel.images)
                && !(this.model.images == null && this.originalModel.images == null)
            ) {
                this.changes.push({
                    'key': 'images',
                    'label': 'Images',
                    'old': this.displayImages(this.originalModel.images),
                    'new': this.displayImages(this.model.images),
                    'value': this.model.images,
                })
            }
        },
        delImage(index) {
            this.imageIndex = index
            this.delImageModal = true
        },
        toggleImagePublic(index) {
            this.model.images[index].public = !this.model.images[index].public
            this.calcChanges()
            this.$emit('validated', 0, null, this)
        },
        validated(isValid, errors) {
            this.isValid = isValid
        },
        submitDeleteImage() {
            this.model.images.splice(this.imageIndex, 1)
            this.calcChanges()
            this.$emit('validated', 0, null, this)
            this.delImageModal = false
        },
        fileAdded(file, response) {
            this.model.images.push(response)
            this.$refs.dropzone.removeFile(file)
            this.calcChanges()
            this.$emit('validated', 0, null, this)
        },
        displayImages(images) {
            let result = []
            for (let image of images) {
                result.push(image.filename + ' (' + (image.public ? 'Public' : 'Not public') + ')')
            }
            return result
        },
    }
}
</script>
