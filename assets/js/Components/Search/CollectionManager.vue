<template>
    <div class="row pbottom-large">
        <div class="col-sm-6 col-sm-offset-6">
            <vue-form-generator
                :schema="collectionSchema"
                :model="collectionModel"
                :options="formOptions"
            />
            <div class="row pbottom-default">
                <div class="col-sm-6">
                    <btn
                        :disabled="collectionModel.managementCollection.length === 0 || collectionArray.length === 0"
                        @click="$emit('addManagementsToSelection', collectionModel.managementCollection)"
                    >
                        Add to selection
                    </btn>
                </div>
                <div class="col-sm-6">
                    <btn
                        :disabled="collectionModel.managementCollection.length === 0 || collectionArray.length === 0"
                        @click="$emit('removeManagementsFromSelection', collectionModel.managementCollection)"
                    >
                        Remove from selection
                    </btn>
                </div>
            </div>
            <div class="row pbottom-default">
                <div class="col-sm-6">
                    <btn
                        :disabled="collectionModel.managementCollection.length === 0"
                        @click="$emit('addManagementsToResults', collectionModel.managementCollection)"
                    >
                        Add to all results
                    </btn>
                </div>
                <div class="col-sm-6">
                    <btn
                        :disabled="collectionModel.managementCollection.length === 0"
                        @click="$emit('removeManagementsFromResults', collectionModel.managementCollection)"
                    >
                        Remove from all results
                    </btn>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import AbstractField from '../FormFields/AbstractField';

export default {
    mixins: [
        AbstractField,
    ],
    props: {
        collectionArray: {
            type: Array,
            default: () => [],
        },
        managements: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            collectionModel: {
                managementCollection: [],
            },
            collectionSchema: {
                fields: {
                    managements: this.createMultiSelect(
                        'Management collection(s)',
                        {
                            model: 'managementCollection',
                            values: this.managements,
                            disabled: false,
                            placeholder: 'Select one or more management collection(s)',
                        },
                        {
                            multiple: true,
                            closeOnSelect: false,
                            loading: false,
                        },
                    ),
                },
            },
            formOptions: {
                validateAfterLoad: true,
                validateAfterChanged: true,
                validationErrorClass: 'has-error',
                validationSuccessClass: 'success',
            },
        };
    },
    methods: {
        addSelection() {

        },
    },
};
</script>
