<template>
    <div
        id="g-recaptcha"
        class="g-recaptcha mbottom-default" />
</template>

<script>
export default {
    props: {
        siteKey: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            widgetId: 0
        }
    },
    mounted() {
        // render the recaptcha widget when the component is mounted
        this.render()
    },
    methods: {
        execute() {
            window.grecaptcha.execute(this.widgetId)
        },
        render() {
            // check if recaptcha is loaded
            if (window.grecaptcha && window.grecaptcha.render) {
                this.widgetId = window.grecaptcha.render('g-recaptcha', {
                    sitekey: this.siteKey,
                    // the callback executed when the user solve the recaptcha
                    callback: (response) => {
                        // emit an event called verify with the response as payload
                        this.$emit('verify', response)
                    }
                })
            }
            else {
                setTimeout(this.render, 1)
            }
        },
    },
}
</script>
