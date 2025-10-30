<script setup lang="ts">
import MacroGoalController from '@/actions/App/Http/Controllers/Settings/MacroGoalController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit as editNutrition } from '@/routes/nutrition';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface MacroGoalPayload {
    daily_calorie_goal: number;
    protein_percentage: number;
    carb_percentage: number;
    fat_percentage: number;
}

interface Props {
    macroGoal: MacroGoalPayload | null;
    status?: string | null;
}

const props = defineProps<Props>();

const status = computed(() => props.status ?? null);

const form = useForm({
    daily_calorie_goal: props.macroGoal
        ? String(props.macroGoal.daily_calorie_goal)
        : '',
    protein_percentage: props.macroGoal
        ? String(props.macroGoal.protein_percentage)
        : '',
    carb_percentage: props.macroGoal
        ? String(props.macroGoal.carb_percentage)
        : '',
    fat_percentage: props.macroGoal
        ? String(props.macroGoal.fat_percentage)
        : '',
});

const totalPercentage = computed(() => {
    const protein = Number(form.protein_percentage) || 0;
    const carbs = Number(form.carb_percentage) || 0;
    const fat = Number(form.fat_percentage) || 0;

    return protein + carbs + fat;
});

const totalIsValid = computed(() => Math.abs(totalPercentage.value - 100) < 0.1);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Nutrition settings',
        href: editNutrition().url,
    },
];

const submit = () => {
    form
        .transform((data) => ({
            daily_calorie_goal: Number(data.daily_calorie_goal) || 0,
            protein_percentage: Number(data.protein_percentage) || 0,
            carb_percentage: Number(data.carb_percentage) || 0,
            fat_percentage: Number(data.fat_percentage) || 0,
        }))
        .put(MacroGoalController.update().url, {
            preserveScroll: true,
        });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Nutrition settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Daily goals"
                    description="Set target calories and macro percentages for your day."
                />

                <div v-if="status" class="grid gap-4">
                    <Alert>
                        <AlertDescription>{{ status }}</AlertDescription>
                    </Alert>
                </div>

                <form @submit.prevent="submit" class="grid gap-6">
                    <div class="grid gap-2 sm:w-72">
                        <Label for="daily_calorie_goal">Daily calorie goal</Label>
                        <Input
                            id="daily_calorie_goal"
                            v-model="form.daily_calorie_goal"
                            name="daily_calorie_goal"
                            type="number"
                            min="800"
                            required
                        />
                        <InputError :message="form.errors.daily_calorie_goal" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="protein_percentage">Protein (%)</Label>
                            <Input
                                id="protein_percentage"
                                v-model="form.protein_percentage"
                                name="protein_percentage"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                required
                            />
                            <InputError :message="form.errors.protein_percentage" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="carb_percentage">Carbs (%)</Label>
                            <Input
                                id="carb_percentage"
                                v-model="form.carb_percentage"
                                name="carb_percentage"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                required
                            />
                            <InputError :message="form.errors.carb_percentage" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="fat_percentage">Fat (%)</Label>
                            <Input
                                id="fat_percentage"
                                v-model="form.fat_percentage"
                                name="fat_percentage"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                required
                            />
                            <InputError :message="form.errors.fat_percentage" />
                        </div>
                    </div>

                    <div
                        class="rounded-md border border-dashed border-border p-3 text-sm"
                        :class="totalIsValid ? 'text-muted-foreground' : 'text-destructive'"
                    >
                        Macro percentages add up to
                        <strong class="text-foreground">
                            {{ totalPercentage.toFixed(1) }}%
                        </strong>
                        .
                        <span v-if="!totalIsValid">
                            Adjust the values so the total equals 100%.
                        </span>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">
                            Save goals
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-if="form.recentlySuccessful"
                                class="text-sm text-muted-foreground"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
