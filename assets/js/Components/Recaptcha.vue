<template>
    <div
        id="g-recaptcha"
        class="g-recaptcha mbottom-default" />
</template>

<script>
export default {
    data() {
        return {
            sitekey: '6LcTj00UAAAAACh6C5LhuQ5-oAtqpV8AhKWyuuMi',
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
            if (window.grecaptcha) {
                this.widgetId = window.grecaptcha.render('g-recaptcha', {
                    sitekey: this.sitekey,
                    // the callback executed when the user solve the recaptcha
                    callback: (response) => {
                        // emit an event called verify with the response as payload
                        this.$emit('verify', response)
                    }
                })
            }
        },
    },
}
</script>
