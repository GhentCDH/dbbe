<template>
    <div style="position:relative" class="dropdown" v-bind:class="{'open':open}">
        <input
            class="form-control"
            type="text"
            autocomplete="off"
            :placeholder="schema.placeholder"
            v-model="tempValue"
            @input="getSuggestions"
            @keydown.enter = "enter"
            @keydown.down = "down"
            @keydown.up = "up"
        >
        <ul class="dropdown-menu" style="width:100%">
            <li v-for="(suggestion, index) in suggestions"
                @click="suggestionClick(suggestion)"
                @mouseover="mouseOver(index)"
            >
              <a :class="{'selected':isSelected(index)}" href="#">{{ suggestion }}
              </a>
            </li>
        </ul>
    </div>
</template>

<script>
    import { abstractField } from 'vue-form-generator'

    export default {
        mixins: [ abstractField ],

        data () {
            return {
                open: false,
                current: -1,
                suggestions: [],
                // Changes to tempValue are not emitted
                tempValue: ''
                // Changes to value are emitted
            }
        },

        mounted () {
            this.$nextTick( () => {
                if (!window.axios) {
                    console.warn('axios is missing. Please download from https://github.com/axios/axios and load the script in the HTML head section!');
                }
            })
        },

        methods: {
            getSuggestions () {
                if (this.tempValue === '') {
                    this.open = false
                    return
                }

                this.open = true

                axios.get(this.schema.url + this.tempValue)
                    .then( (response) => {
                        this.suggestions = response.data
                    })
                    .catch( (error) => {
                        console.log(error)
                    })
            },
            suggestionClick (suggestion) {
                this.tempValue = suggestion
                this.value = this.tempValue
                this.current = -1
                this.open = false
            },
            enter () {
                if (this.current !== -1) {
                    this.tempValue = this.suggestions[this.current]
                }
                this.value = this.tempValue
                this.current = -1
                this.open = false
            },
            down () {
                if (this.current < this.suggestions.length - 1) {
                    this.current++
                }
            },
            up () {
                if (this.current > - 1) {
                    this.current--
                }
            },
            isSelected (index) {
                return index === this.current
            },
            mouseOver (index) {
                this.current = index
            }
        }
    }
</script>
