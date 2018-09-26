<template>
    <div class="row">
        <div class="col-xs-10">
            <vue-form-generator
                :schema="schema"
                :model="model"
            />
        </div>
        <div
            v-if="Object.keys(conditions).length"
            class="col-xs-2 ptop-default"
        >
            <a
                v-if="conditions.add"
                href="#"
                class="action"
                :title="titles.add ? titles.add : 'Add a new ' + name"
                @click.prevent="$emit('add')"
            >
                <i class="fa fa-plus" />
            </a>
            <a
                v-if="conditions.edit"
                href="#"
                class="action"
                :title="titles.edit ? titles.edit : 'Edit the selected ' + name"
                @click.prevent="$emit('edit')"
            >
                <i class="fa fa-pencil-square-o" />
            </a>
            <a
                v-if="conditions.merge"
                href="#"
                class="action"
                :title="titles.merge ? titles.merge : 'Merge the selected ' + name + ' with another ' + name"
                @click.prevent="$emit('merge')"
            >
                <i class="fa fa-compress" />
            </a>
            <a
                v-if="conditions.migrate"
                href="#"
                class="action"
                :title="titles.migrate ? titles.migrate : 'Migrate the selected ' + name + ' to a ' + toName"
                @click.prevent="$emit('migrate')"
            >
                <i class="fa fa-arrow-right" />
            </a>
            <a
                v-if="conditions.del"
                href="#"
                class="action"
                :title="titles.del ? titles.del : 'Delete the selected ' + name"
                @click.prevent="$emit('del')"
            >
                <i class="fa fa-trash-o" />
            </a>
        </div>
    </div>
</template>
<script>
export default {
    props: {
        schema: {
            type: Object,
            default: () => {return {}}
        },
        model: {
            type: Object,
            default: () => {return {}}
        },
        name: {
            type: String,
            default: ''
        },
        toName: {
            type: String,
            default: ''
        },
        conditions: {
            type: Object,
            default: () => {return {}}
        },
        titles: {
            type: Object,
            default: () => {return {}}
        },
    },
}
</script>
