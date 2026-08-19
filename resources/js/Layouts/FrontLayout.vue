<script setup>
import CustomCursor from '../Components/Common/CustomCursor.vue'
import Navbar from '../Components/Layout/Front/Navbar.vue'
import FlashMessage from '../Components/Common/FlashMessage.vue'
</script>
<template>
    <div class="min-h-screen bg-linear-to-br from-slate-900 via-blue-950 to-slate-900 front-layout">
        <Navbar />
        <main class="container mx-auto px-4 py-8 sm:py-14">
            <FlashMessage />
            <Transition mode="out-in" enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-4">
                <div :key="$page.component">
                    <slot />
                </div>
            </Transition>
        </main>
    </div>
    <CustomCursor />
</template>
