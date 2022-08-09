<template>
    <div class="delete-span-box">
        <div v-if="Array.isArray(this.itemvalue)">
            <div v-if="this.itemvalue.length">
                <h4>{{itemlabel}}</h4>
                <div v-for="(val, ind) in itemvalue" :key="val.id" class="delete-span-container">{{val.name}} <i class="fa fa-close delete-span-icon" @click="onDelete(ind)"></i></div>
            </div>
        </div>
        <div v-else-if="typeof this.itemvalue === 'string' || this.itemkey === 'year_from' || this.itemkey === 'year_to'">
            <div v-if="this.itemvalue !== ''">
                <h4>{{itemlabel}}</h4>
                <div class="delete-span-container">{{itemvalue}} <i class="fa fa-close delete-span-icon" @click="onDelete(-1)"></i></div>
            </div>
        </div>
        <div v-else>
            <h4>{{itemlabel}}</h4>
            <div class="delete-span-container">{{itemvalue.name}} <i class="fa fa-close delete-span-icon" @click="onDelete(-1)"></i></div>
        </div>
    </div>
</template>
<script>
export default {
    props: ['itemkey', 'itemvalue', 'itemlabel'],
    methods: {
        onDelete(index) {
            this.$emit('deleted', {
                key: this.itemkey,
                index: index,
            });
        }
    },
}
</script>