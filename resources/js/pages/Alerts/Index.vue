<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { describeSearch, formatDate } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that match your saved searches.">
        <ul v-if="alerts.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="alert in alerts.data" :key="alert.id" class="flex flex-col gap-2">
                <p class="text-xs text-slate-500">
                    {{ formatDate(alert.created_at) }}
                    <template v-if="alert.saved_search">
                        &middot; matched {{ describeSearch(alert.saved_search) }}
                    </template>
                </p>
                <ListingCard :listing="alert.listing" />
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. You'll get one when a new listing matches one of your
            <Link href="/saved-searches" class="underline">saved searches</Link>.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
