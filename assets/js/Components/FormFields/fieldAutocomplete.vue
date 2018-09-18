<template>
    <div class="dropdown" :class="{open: open}">
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
            @keydown.esc = "esc"
        >
        <ul class="dropdown-menu" style="width:100%">
            <li v-for="(suggestion, index) in suggestions"
                @click="suggestionClick(suggestion)"
                @mouseover="mouseOver(index)"
            >
              <a :class="{selected: isSelected(index)}" href="#">{{ suggestion }}
              </a>
            </li>
        </ul>
        <div v-if="openRequests > 0" class="spinner">
        </div>
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
                // Changes to inherited value are emitted
                tempValue: '',
                oldValue: '',
                openRequests: 0,
                cancel: null
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

                if (this.openRequests > 0) {
                    this.cancel('Operation canceled by newer request')
                    this.open = false
                }

                this.current = -1
                this.oldValue = this.tempValue

                this.openRequests++

                axios.get(this.schema.url + this.tempValue, {
                    cancelToken: new axios.CancelToken((c) => {this.cancel = c})
                })
                    .then( (response) => {
                        this.openRequests--
                        this.suggestions = response.data
                        this.open = true
                    })
                    .catch( (error) => {
                        this.openRequests--
                        if (!axios.isCancel(error)) {
                            console.log(error)
                        }
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
                    this.tempValue = this.suggestions[this.current]
                }
            },
            up () {
                if (this.current > - 1) {
                    this.current--
                    if (this.current == -1) {
                        this.tempValue = this.oldValue
                    }
                    else {
                        this.tempValue = this.suggestions[this.current]
                    }
                }
            },
            esc () {
                this.current = -1
                this.open = false
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
