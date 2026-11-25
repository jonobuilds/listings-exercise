<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import { describeSearch, listingsUrl } from '../../format';

defineProps({
    savedSearches: { type: Array, required: true },
});

function remove(search) {
    router.delete(`/saved-searches/${search.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout
        heading="Saved searches"
        subheading="You'll get an alert when a new listing matches one of these."
    >
        <ul v-if="savedSearches.length" class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
            <li
                v-for="search in savedSearches"
                :key="search.id"
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <span class="font-medium">{{ describeSearch(search) }}</span>

                <span class="flex items-center gap-4 text-sm">
                    <Link :href="listingsUrl(search.criteria)" class="text-slate-600 hover:text-slate-900">
                        View current matches
                    </Link>
                    <button
                        type="button"
                        class="text-red-700 hover:underline"
                        @click="remove(search)"
                    >
                        Delete
                    </button>
                </span>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No saved searches yet. Filter the
            <Link href="/" class="underline">listings</Link>
            and choose "Save this search".
        </p>
    </AppLayout>
</template>
