<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';
import { weekly as dashboardWeekly } from '@/routes/dashboard';

type MacroKey = 'protein' | 'carb' | 'fat';

interface WeeklyDay {
    date: string;
    weekday: string;
    calories: number;
    burned: number;
    net: number;
    macros: Record<MacroKey, number>;
}

interface WeeklyTotals {
    calories: number;
    burned: number;
    net: number;
    protein: number;
    carb: number;
    fat: number;
}

interface WeeklyPayload {
    start: string;
    end: string;
    days: WeeklyDay[];
    totals: WeeklyTotals;
}

interface MacroGoalPayload {
    daily_calorie_goal: number;
    protein_percentage: number;
    carb_percentage: number;
    fat_percentage: number;
    targets: Record<MacroKey, number>;
}

interface WeeklyFoodEntry {
    id: number;
    name: string;
    calories: number;
    protein: number;
    carb: number;
    fat: number;
    quantity: number;
    serving_unit: string | null;
    consumed_on: string;
    weekday: string;
}

interface WeeklyBurnEntry {
    id: number;
    calories: number;
    description: string | null;
    recorded_on: string;
    weekday: string;
}

interface Props {
    week: WeeklyPayload;
    macroGoal: MacroGoalPayload | null;
    date: {
        current: string;
        previous: string;
        next: string;
    };
    foods: WeeklyFoodEntry[];
    burns: WeeklyBurnEntry[];
}

const props = defineProps<Props>();

const macroKeys: MacroKey[] = ['protein', 'carb', 'fat'];

const macroLabel = (key: MacroKey): string => (key === 'carb' ? 'Carbs' : key.charAt(0).toUpperCase() + key.slice(1));

const macroBarClasses: Record<MacroKey, string> = {
    protein: 'bg-emerald-500/80',
    carb: 'bg-sky-500/80',
    fat: 'bg-amber-500/80',
};

const macroChipClasses: Record<MacroKey, string> = {
    protein: 'text-emerald-500',
    carb: 'text-sky-500',
    fat: 'text-amber-500',
};

const gramsFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

const caloriesFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 0,
});

const percentageFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

const dayCount = computed(() => props.week.days.length || 7);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Weekly overview',
        href: dashboardWeekly().url,
    },
];

const weekRangeLabel = computed(() => `${props.week.start} → ${props.week.end}`);

const macroChartData = computed(() =>
    macroKeys.map((key) => {
        const actual = props.week.totals[key];
        const goalPerDay = props.macroGoal?.targets[key] ?? null;
        const goal = goalPerDay !== null ? goalPerDay * dayCount.value : null;
        const goalPercent = goal !== null && goal > 0 ? (actual / goal) * 100 : null;

        return {
            key,
            label: macroLabel(key),
            actual,
            goal,
            percentOfGoal: goalPercent !== null ? Math.min(goalPercent, 999.9) : null,
        };
    }),
);

const weeklyCaloriesGoal = computed(() => {
    if (!props.macroGoal) {
        return null;
    }

    return props.macroGoal.daily_calorie_goal * dayCount.value;
});

const macroTotalsCalories = computed(() => props.week.totals.calories);

const macroCaloriesBreakdown = computed(() =>
    macroKeys.map((key) => {
        const grams = props.week.totals[key];
        const caloriesPerGram = key === 'fat' ? 9 : 4;
        const calories = grams * caloriesPerGram;
        const percent = macroTotalsCalories.value > 0 ? (calories / macroTotalsCalories.value) * 100 : null;

        return {
            key,
            label: macroLabel(key),
            calories,
            percent: percent !== null ? Math.min(percent, 999.9) : null,
        };
    }),
);

const dailyCaloriesGoal = computed(() => props.macroGoal?.daily_calorie_goal ?? null);

const dailyCaloriesData = computed(() => {
    const goal = dailyCaloriesGoal.value;
    const values = props.week.days.map((day) => day.calories);
    const maxValue = Math.max(goal ?? 0, ...(values.length ? values : [0]));
    const scale = maxValue > 0 ? maxValue : 1;

    return props.week.days.map((day) => {
        const actualHeight = Math.min(100, Math.max(0, (day.calories / scale) * 100));
        const goalHeight = goal !== null ? Math.min(100, Math.max(0, (goal / scale) * 100)) : null;

        return {
            ...day,
            actualHeight,
            goalHeight,
        };
    });
});

const formatPercentage = (value: number | null): string => {
    if (value === null || Number.isNaN(value)) {
        return '--';
    }

    return `${percentageFormatter.format(value)}%`;
};

type FoodSortKey = 'calories' | 'protein' | 'carb' | 'fat';

const foodSortKey = ref<FoodSortKey>('calories');

const foodSortOptions: FoodSortKey[] = ['calories', 'protein', 'carb', 'fat'];

const sortedFoods = computed(() => {
    const key = foodSortKey.value;

    return [...props.foods].sort((a, b) => (b[key] ?? 0) - (a[key] ?? 0));
});

const foodSortLabel = computed(() => {
    switch (foodSortKey.value) {
        case 'calories':
            return 'Calories';
        case 'protein':
            return 'Protein';
        case 'carb':
            return 'Carbs';
        case 'fat':
            return 'Fat';
        default:
            return 'Calories';
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Weekly overview" />

        <div class="space-y-6">
            <div class="flex flex-col gap-4 rounded-lg border border-border bg-card px-4 py-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-foreground md:text-xl">Weekly overview</h1>
                    <p class="text-muted-foreground text-sm">
                        Tracking calories and macronutrients from {{ weekRangeLabel }}.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="dashboardWeekly({ query: { date: date.previous } })"
                        as="button"
                        class="inline-flex items-center rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                    >
                        Previous week
                    </Link>
                    <Link
                        :href="dashboardWeekly({ query: { date: date.next } })"
                        as="button"
                        class="inline-flex items-center rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                    >
                        Next week
                    </Link>
                    <Link
                        :href="dashboardWeekly({})"
                        as="button"
                        class="inline-flex items-center rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                    >
                        Jump to current
                    </Link>
                    <Link
                        :href="dashboard()"
                        as="button"
                        class="inline-flex items-center rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
                    >
                        Back to dashboard
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Weekly macro totals</CardTitle>
                        <CardDescription>
                            Actual vs goal totals for the week.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div
                            v-for="macro in macroChartData"
                            :key="macro.key"
                            class="space-y-2"
                        >
                            <div class="flex items-center justify-between text-sm font-medium text-foreground">
                                <span>{{ macro.label }}</span>
                                <span>
                                    {{ gramsFormatter.format(macro.actual) }}g
                                    <template v-if="macro.goal !== null">
                                        / {{ gramsFormatter.format(macro.goal) }}g
                                    </template>
                                </span>
                            </div>
                            <div class="relative h-3 overflow-hidden rounded-full bg-muted">
                                <div
                                    v-if="macro.goal !== null"
                                    class="absolute inset-y-0 w-full bg-muted-foreground/10"
                                ></div>
                                <div
                                    :class="['absolute inset-y-0 rounded-full', macroBarClasses[macro.key]]"
                                    :style="{ width: macro.goal !== null ? `${Math.min(100, macro.percentOfGoal ?? 0)}%` : '100%' }"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Actual</span>
                                <span v-if="macro.goal !== null">
                                    {{ formatPercentage(macro.percentOfGoal) }} of goal
                                </span>
                                <span v-else class="italic">Set a macro goal to compare.</span>
                            </div>
                        </div>
                        <div class="rounded-md border border-border/70 bg-muted/10 px-3 py-2 text-xs text-muted-foreground space-y-1">
                            <p class="font-medium text-foreground text-sm">Weekly calories from macros</p>
                            <div
                                v-for="macro in macroCaloriesBreakdown"
                                :key="`cal-${macro.key}`"
                                class="flex items-center justify-between"
                            >
                                <span class="font-medium" :class="macroChipClasses[macro.key]">
                                    {{ macro.label }}
                                </span>
                                <span>
                                    {{ caloriesFormatter.format(macro.calories) }} kcal
                                    <template v-if="macro.percent !== null">
                                        · {{ formatPercentage(macro.percent) }}
                                    </template>
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily calorie progress</CardTitle>
                        <CardDescription>
                            Compare each day against your calorie goal.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                            <span class="flex items-center gap-2">
                                <span class="inline-block size-3 rounded-full bg-primary"></span>
                                Actual calories
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="inline-block size-3 rounded-full bg-muted-foreground/30"></span>
                                Calorie goal
                            </span>
                        </div>
                        <div class="flex items-end gap-4 overflow-x-auto pb-2">
                            <div
                                v-for="day in dailyCaloriesData"
                                :key="day.date"
                                class="flex w-16 flex-col items-center gap-2 text-center"
                            >
                                <div class="relative flex h-48 w-10 items-end justify-center">
                                    <div
                                        v-if="day.goalHeight !== null"
                                        class="absolute bottom-0 w-8 rounded-t bg-muted-foreground/20"
                                        :style="{ height: `${day.goalHeight}%` }"
                                    ></div>
                                    <div
                                        class="relative w-6 rounded-t bg-primary"
                                        :style="{ height: `${day.actualHeight}%` }"
                                    ></div>
                                </div>
                                <div class="flex flex-col text-xs">
                                    <span class="font-medium text-muted-foreground">{{ day.weekday }}</span>
                                    <span class="font-semibold text-foreground">
                                        {{ caloriesFormatter.format(day.calories) }} kcal
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            <template v-if="dailyCaloriesGoal !== null">
                                Weekly goal: {{ caloriesFormatter.format(dailyCaloriesGoal * dayCount) }} kcal · Daily goal: {{ caloriesFormatter.format(dailyCaloriesGoal) }} kcal
                            </template>
                            <template v-else>
                                Set a daily calorie goal to compare against progress.
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader class="space-y-3">
                        <CardTitle>Foods logged this week</CardTitle>
                        <CardDescription>
                            Sorted by highest <span class="font-semibold text-foreground">{{ foodSortLabel }}</span>.
                        </CardDescription>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="option in foodSortOptions"
                                :key="option"
                                type="button"
                                variant="outline"
                                class="rounded-full px-3 py-1 text-xs font-semibold capitalize"
                                :class="foodSortKey === option ? 'border-primary text-primary' : ''"
                                @click="foodSortKey = option"
                            >
                                {{ option }}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="sortedFoods.length === 0" class="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No food entries logged during this week.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="entry in sortedFoods"
                                :key="entry.id"
                                class="flex flex-col gap-2 rounded-md border border-border p-3 text-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-foreground">
                                            {{ entry.name }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ entry.weekday }} · {{ entry.consumed_on }} · {{ entry.quantity }}{{ entry.serving_unit ? ' ' + entry.serving_unit : '' }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-foreground">
                                            {{ caloriesFormatter.format(entry.calories) }} kcal
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Highest {{ foodSortKey }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3 text-xs font-medium">
                                    <span :class="['flex items-center gap-1', macroChipClasses.protein]">
                                        Protein: {{ gramsFormatter.format(entry.protein) }}g
                                    </span>
                                    <span :class="['flex items-center gap-1', macroChipClasses.carb]">
                                        Carbs: {{ gramsFormatter.format(entry.carb) }}g
                                    </span>
                                    <span :class="['flex items-center gap-1', macroChipClasses.fat]">
                                        Fat: {{ gramsFormatter.format(entry.fat) }}g
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Calories burned</CardTitle>
                        <CardDescription>
                            Workouts and adjustments recorded during the week.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="burns.length === 0" class="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No calorie burn entries for this week.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="entry in burns"
                                :key="entry.id"
                                class="flex flex-col gap-2 rounded-md border border-border p-3 text-sm"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-semibold text-foreground">
                                            {{ entry.description ?? 'Calories burned' }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ entry.weekday }} · {{ entry.recorded_on }}
                                        </p>
                                    </div>
                                    <p class="text-sm font-semibold text-foreground">
                                        {{ caloriesFormatter.format(entry.calories) }} kcal
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
