<template>
    <div>
        <div v-if="Array.isArray(this.modelvalue)">
            <div v-if="this.modelvalue.length">
                <h4>{{modelkey}}</h4>
                <div v-for="(val, ind) in modelvalue" :key="val.id" class="delete-span-container">{{val.name}} <i class="fa fa-close delete-span-icon" @click="onDelete(ind)"></i></div>
            </div>
        </div>
        <div v-else-if="typeof this.modelvalue === 'string' || this.modelkey === 'year_from' || this.modelkey === 'year_to'">
            <div v-if="this.modelvalue !== ''">
                <h4>{{modelkey}}</h4>
                <div class="delete-span-container">{{modelvalue}} <i class="fa fa-close delete-span-icon" @click="onDelete(-1)"></i></div>
            </div>
        </div>
        <div v-else>
            <h4>{{modelkey}}</h4>
            <div class="delete-span-container">{{modelvalue.name}} <i class="fa fa-close delete-span-icon" @click="onDelete(-1)"></i></div>
        </div>
    </div>
</template>
<script>
export default {
    props: ['modelkey', 'modelvalue'],
    methods: {
        onDelete(index) {
            this.$emit('deleted', {
                key: this.modelkey,
                index: index,
            });
        }
    },
}
</script>