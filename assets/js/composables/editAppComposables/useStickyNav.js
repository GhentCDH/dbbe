import { ref, watch } from 'vue'

export function useStickyNav(anchorRef) {
    const scrollY = ref(null)
    const isSticky = ref(false)
    const stickyStyle = ref({})

    watch(scrollY, () => {
        if (anchorRef.value) {
            const rect = anchorRef.value.getBoundingClientRect()
            if (rect.top < 30) {
                isSticky.value = true
                stickyStyle.value = { width: rect.width + 'px' }
            } else {
                isSticky.value = false
                stickyStyle.value = {}
            }
        }
    })

    const initScrollListener = () => {
        window.addEventListener('scroll', () => {
            scrollY.value = Math.round(window.scrollY)
        })
    }

    return {
        scrollY,
        isSticky,
        stickyStyle,
        initScrollListener,
    }
}
