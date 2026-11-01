<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{ settings: Record<string, number> }>();
setLayoutProps({
    breadcrumbs: [{ title: 'Paramètres', href: '/staff/settings' }],
});
const form = useForm({ ...props.settings });
const fields = [
    ['home_fr_offers_count', 'Offres — français'],
    ['home_fr_calls_count', "Appels d'offres — français"],
    ['home_fr_notices_count', 'Avis — français'],
    ['home_ar_offers_count', 'Offres — arabe'],
    ['home_ar_calls_count', "Appels d'offres — arabe"],
    ['home_ar_notices_count', 'Avis — arabe'],
] as const;
</script>

<template>
    <Head title="Paramètres" />
    <div class="flex flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <div>
            <h1 class="text-3xl font-normal text-[#5a5c69]">Paramètres</h1>
            <p class="mt-2 text-sm text-[#858796]">
                Nombre d’annonces affichées sur la page d’accueil, par langue et
                famille.
            </p>
        </div>
        <form
            class="grid max-w-3xl gap-5 rounded border border-[#e3e6f0] bg-[#f8f9fc] p-5 md:grid-cols-2"
            @submit.prevent="form.put('/staff/settings')"
        >
            <label
                v-for="[key, label] in fields"
                :key="key"
                class="grid gap-1 text-sm"
                ><span>{{ label }}</span
                ><Input
                    v-model.number="form[key]"
                    type="number"
                    min="1"
                    max="50"
                    required /><InputError :message="form.errors[key]"
            /></label>
            <div class="md:col-span-2">
                <Button type="submit" :disabled="form.processing"
                    >Enregistrer</Button
                >
            </div>
        </form>
    </div>
</template>
