<template>
    <div>
        <alert
            v-for="(item, index) in alerts"
            :key="index"
            :type="item.type"
            dismissible
            @dismissed="$emit('dismiss', index)"
        >
            <p>{{ item.message }}</p>
            <p v-if="item.extra">
                {{ item.extra }}
            </p>
            <p v-if="item.login">
                Is it possible your login timed out? Try
                <btn @click="login(index)">
                    logging in
                </btn>
                again.
            </p>
        </alert>
    </div>
</template>
<script>
export default {
    props: {
        alerts: {
            type: Array,
            default: () => {return []}
        },
    },
    methods: {
        login(index) {
            window.open(this.$root.$children[0].urls.login + '?close=true');
            this.$emit('dismiss', index)
        },
    }
}
</script>
