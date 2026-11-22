<script setup>
// The Fandaqah logo: original artwork on light surfaces, the inverse lockup in dark mode
// (or always, with `inverse`, on navy panels).
defineOptions({ inheritAttrs: false });
defineProps({ inverse: Boolean });
</script>

<template>
    <img v-if="inverse" src="/brand/logo-inverse.png" alt="Fandaqah" v-bind="$attrs" />
    <template v-else>
        <img src="/brand/logo.png" alt="Fandaqah" v-bind="$attrs" class="dark:hidden" />
        <img src="/brand/logo-inverse.png" alt="Fandaqah" v-bind="$attrs" class="hidden dark:block" />
    </template>
</template>
