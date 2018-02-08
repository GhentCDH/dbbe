<template>
    <div class="modal fade" id={{ id }} tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"></h4>
                    {{ title }}
                </div>
                <div class="modal-body" @keyup.enter="submitDelete">
                    <div role="alert" class="alert alert-dismissible alert-danger" v-if="error">
                        <button aria-label="Close" data-dismiss="alert" class="close" type="button"><span aria-hidden="true">×</span></button>
                        <span class="sr-only">Error</span>
                        {{ error }}
                    </div>
                    {{ message }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" @click="submit">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        methods: {
            submit () {
                this.$emit('submit')
            }
    }
</script>
