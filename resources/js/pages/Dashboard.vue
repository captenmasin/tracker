<script setup lang="ts">
import FoodBarcodeController from '@/actions/App/Http/Controllers/FoodBarcodeController';
import CalorieBurnEntryController from '@/actions/App/Http/Controllers/CalorieBurnEntryController';
import FoodEntryController from '@/actions/App/Http/Controllers/FoodEntryController';
import BarcodeScanner from '@/Pages/Dashboard/components/BarcodeScanner.vue';
import {Tabs, TabsContent, TabsList, TabsTrigger} from '@/components/ui/tabs'
import InputError from '@/components/InputError.vue';
import {Badge} from '@/components/ui/badge';
import {Button} from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {Input} from '@/components/ui/input';
import {Label} from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger} from '@/components/ui/sheet';
import nutrition from '@/routes/nutrition';
import {dashboard} from '@/routes';
import {type BreadcrumbItem} from '@/types';
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import {
    ArrowRight,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Flame,
    PlusCircle,
    Scan,
    Trash2,
    UtensilsCrossed,
} from 'lucide-vue-next';
import {computed, ref, watch} from 'vue';
import {toast} from 'vue-sonner';

type MacroKey = 'protein' | 'carb' | 'fat';

interface MacroProgress {
    consumed: number;
    goal: number | null;
    remaining: number | null;
    allowance: number;
    percentage: number | null;
}

interface FoodEntrySummary {
    id: number;
    name: string;
    quantity: number;
    serving_unit: string | null;
    calories: number;
    protein_grams: number;
    carb_grams: number;
    fat_grams: number;
    consumed_on: string;
    source: string;
    barcode: string | null;
}

interface BurnEntrySummary {
    id: number;
    calories: number;
    description: string | null;
    recorded_on: string;
}

interface Summary {
    date: {
        current: string;
        display: string;
        previous: string;
        next: string;
        isToday: boolean;
    };
    calories: {
        consumed: number;
        burned: number;
        net: number;
        goal: number | null;
        remaining: number | null;
    };
    macros: Record<MacroKey, MacroProgress>;
    macro_goal: {
        daily_calorie_goal: number;
        protein_percentage: number;
        carb_percentage: number;
        fat_percentage: number;
        targets: Record<MacroKey, number>;
    } | null;
    entries: {
        foods: FoodEntrySummary[];
        burns: BurnEntrySummary[];
    };
    weekly: {
        start: string;
        end: string;
        days: Array<{
            date: string;
            weekday: string;
            calories: number;
            burned: number;
            net: number;
            macros: Record<MacroKey, number>;
        }>;
        totals: {
            calories: number;
            burned: number;
            net: number;
            protein: number;
            carb: number;
            fat: number;
        };
    };
}

interface FoodOption {
    id: number;
    name: string;
    barcode: string | null;
    serving_size: number;
    serving_unit: string;
    calories_per_serving: number;
    protein_grams: number;
    carb_grams: number;
    fat_grams: number;
}

interface Props {
    summary: Summary;
    foods: FoodOption[];
    status?: string | null;
}

const props = defineProps<Props>();

const summary = computed(() => props.summary);
const foods = computed(() => props.foods);
const status = computed(() => props.status ?? null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const nutritionSettingsRoute = nutrition.edit();

const activeIntakeTab = ref<'library' | 'manual'>('library');
const scannerActive = ref(false);
const barcodeNotice = ref<{ variant: 'default' | 'destructive'; message: string } | null>(null);
const selectedDateInput = ref(summary.value.date.current);
const referenceAmountLabel = ref<string | null>(null);
const referenceUnitLabel = ref<string>('g');

const libraryForm = useForm({
    food_id: '',
    quantity: '1',
    consumed_on: summary.value.date.current,
    source: 'food' as const,
});

const manualForm = useForm({
    name: '',
    calories: '',
    protein_grams: '',
    carb_grams: '',
    fat_grams: '',
    quantity: '1',
    serving_size_value: '',
    serving_unit: 'g',
    serving_unit_raw: 'g',
    consumed_on: summary.value.date.current,
    barcode: '',
    source: 'manual' as const,
});

const burnForm = useForm({
    calories: '',
    recorded_on: summary.value.date.current,
    description: '',
});

const macroList = computed(() =>
    (Object.entries(summary.value.macros) as Array<[MacroKey, MacroProgress]>).map(
        ([key, progress]) => ({
            key,
            label: key === 'carb' ? 'Carbs' : key.charAt(0).toUpperCase() + key.slice(1),
            progress,
        }),
    ),
);

const selectedFood = computed(() => {
    const id = Number(libraryForm.food_id);

    return foods.value.find((food) => food.id === id);
});

const libraryTotals = computed(() => {
    const food = selectedFood.value;

    if (!food) {
        return null;
    }

    const quantity = Math.max(Number(libraryForm.quantity) || 1, 0.01);

    return {
        calories: food.calories_per_serving * quantity,
        protein: food.protein_grams * quantity,
        carbs: food.carb_grams * quantity,
        fat: food.fat_grams * quantity,
    };
});

const manualTotals = computed(() => {
    const quantity = Math.max(Number(manualForm.quantity) || 1, 0.01);

    return {
        calories: (Number(manualForm.calories) || 0) * quantity,
        protein: (Number(manualForm.protein_grams) || 0) * quantity,
        carbs: (Number(manualForm.carb_grams) || 0) * quantity,
        fat: (Number(manualForm.fat_grams) || 0) * quantity,
    };
});

const caloriesFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 0,
});

const gramsFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

const percentageFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

const amountFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 2,
});

const macroCircleRadius = 42;
const macroCircleCircumference = 2 * Math.PI * macroCircleRadius;

interface MacroCircleStyle {
    strokeDasharray: string;
    strokeDashoffset: number;
}

const macroCircleStyle = (percentage: number | null): MacroCircleStyle => {
    const circumference = macroCircleCircumference;

    if (percentage === null) {
        return {
            strokeDasharray: `${circumference} ${circumference}`,
            strokeDashoffset: circumference,
        };
    }

    const clamped = Math.min(Math.max(percentage, 0), 100);
    const offset = circumference - (clamped / 100) * circumference;

    return {
        strokeDasharray: `${circumference} ${circumference}`,
        strokeDashoffset: offset,
    };
};

const servingUnitOptions = [
    {label: 'grams (g)', value: 'g'},
    {label: 'milliliters (ml)', value: 'ml'},
];

watch(
    () => status.value,
    (newStatus) => {
        if (newStatus) {
            toast.success(newStatus);
        }
    },
    {immediate: true},
);

watch(
    () => barcodeNotice.value,
    (notice) => {
        if (!notice) {
            return;
        }

        const notify = notice.variant === 'destructive' ? toast.error : toast.success;
        notify(notice.message);

        barcodeNotice.value = null;
    },
);

const navigateToDate = (value: string) => {
    if (!value || value === summary.value.date.current) {
        return;
    }

    router.visit(
        dashboard({
            query: {
                date: value,
            },
        }).url,
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const goToPrevious = () => {
    navigateToDate(summary.value.date.previous);
};

const goToNext = () => {
    if (!summary.value.date.isToday) {
        navigateToDate(summary.value.date.next);
    }
};

const submitLibrary = () => {
    libraryForm
        .transform((data) => {
            const quantity = Math.max(Number(data.quantity) || 1, 0.01);
            const payload: Record<string, unknown> = {
                consumed_on: data.consumed_on,
                quantity,
                source: data.source,
            };

            if (data.food_id) {
                payload.food_id = Number(data.food_id);
            }

            return payload;
        })
        .post(FoodEntryController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                libraryForm.reset('quantity');
                libraryForm.quantity = '1';
                // barcodeNotice.value = {
                //     variant: 'default',
                //     message: 'Entry logged successfully.',
                // };
            },
        });
};

const submitManual = () => {
    manualForm
        .transform((data) => {
            const quantity = Math.max(Number(data.quantity) || 1, 0.01);
            const servingSizeValue = Number(data.serving_size_value) || 0;
            const servingUnit = data.serving_unit || 'g';

            const payload: Record<string, unknown> = {
                name: data.name,
                consumed_on: data.consumed_on,
                serving_unit:
                    servingSizeValue > 0
                        ? `${servingSizeValue} ${servingUnit}`
                        : servingUnit,
                serving_unit_raw: servingUnit,
                serving_size_value: servingSizeValue > 0 ? servingSizeValue : null,
                quantity,
                calories: (Number(data.calories) || 0) * quantity,
                protein_grams: (Number(data.protein_grams) || 0) * quantity,
                carb_grams: (Number(data.carb_grams) || 0) * quantity,
                fat_grams: (Number(data.fat_grams) || 0) * quantity,
                source: data.source,
            };

            if (data.barcode) {
                payload.barcode = data.barcode;
            }

            return payload;
        })
        .post(FoodEntryController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                manualForm.reset();
                manualForm.quantity = '1';
                manualForm.serving_size_value = '';
                manualForm.serving_unit = 'g';
                manualForm.serving_unit_raw = 'g';
                manualForm.consumed_on = summary.value.date.current;
                manualForm.source = 'manual';
            },
        });
};

const submitBurn = () => {
    burnForm
        .transform((data) => ({
            calories: Number(data.calories) || 0,
            recorded_on: data.recorded_on,
            description: data.description || '',
        }))
        .post(CalorieBurnEntryController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                burnForm.reset();
                burnForm.recorded_on = summary.value.date.current;
            },
        });
};

const removeFoodEntry = (id: number) => {
    router.delete(FoodEntryController.destroy(id).url, {
        preserveScroll: true,
    });
};

const removeBurnEntry = (id: number) => {
    router.delete(CalorieBurnEntryController.destroy(id).url, {
        preserveScroll: true,
    });
};

const handleBarcodeDetected = async (value: string) => {
    const trimmed = value.trim();

    scannerActive.value = false;

    if (!trimmed) {
        return;
    }

    const existingFood = foods.value.find((food) => food.barcode === trimmed);

    if (existingFood) {
        libraryForm.food_id = existingFood.id.toString();
        libraryForm.quantity = '1';
        referenceAmountLabel.value = existingFood.serving_size
            ? amountFormatter.format(existingFood.serving_size)
            : null;
        referenceUnitLabel.value = existingFood.serving_unit ?? 'g';
        barcodeNotice.value = {
            variant: 'default',
            message: `Loaded ${existingFood.name} from your library.`,
        };
        return;
    }

    try {
        const response = await fetch(
            FoodBarcodeController({barcode: trimmed}).url,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        if (response.ok) {
            const data = await response.json();
            if (data.source === 'library' && data.food?.id) {
                libraryForm.food_id = data.food.id.toString();
                libraryForm.quantity = '1';
                referenceAmountLabel.value = data.food.serving_size
                    ? amountFormatter.format(data.food.serving_size)
                    : null;
                referenceUnitLabel.value = data.food.serving_unit ?? 'g';
                barcodeNotice.value = {
                    variant: 'default',
                    message: `Loaded ${data.food.name} from your library.`,
                };
                return;
            }

            if (data.source === 'external' && data.food) {
                activeIntakeTab.value = 'manual';

                const formatNumber = (value: unknown): string => {
                    if (typeof value === 'number') {
                        return amountFormatter.format(value);
                    }

                    if (typeof value === 'string' && value !== '') {
                        const numeric = Number(value);
                        if (!Number.isNaN(numeric)) {
                            return amountFormatter.format(numeric);
                        }
                    }

                    return '';
                };

                manualForm.name = data.food.name ?? '';
                manualForm.barcode = trimmed;
                manualForm.quantity = '1';
                manualForm.serving_size_value = formatNumber(
                    data.food.reference_quantity ?? data.food.serving_size,
                );
                manualForm.serving_unit = data.food.serving_unit ?? 'g';
                manualForm.serving_unit_raw = manualForm.serving_unit;
                manualForm.calories = formatNumber(
                    data.food.calories_total ?? data.food.calories_per_serving,
                );
                manualForm.protein_grams = formatNumber(
                    data.food.protein_total ?? data.food.protein_grams,
                );
                manualForm.carb_grams = formatNumber(
                    data.food.carb_total ?? data.food.carb_grams,
                );
                manualForm.fat_grams = formatNumber(
                    data.food.fat_total ?? data.food.fat_grams,
                );

                const referenceQuantityNumeric = Number(
                    formatNumber(data.food.reference_quantity ?? data.food.serving_size),
                );

                if (!Number.isNaN(referenceQuantityNumeric) && referenceQuantityNumeric > 0) {
                    referenceAmountLabel.value = amountFormatter.format(referenceQuantityNumeric);
                } else {
                    referenceAmountLabel.value = null;
                }

                referenceUnitLabel.value = data.food.serving_unit ?? 'g';

                barcodeNotice.value = {
                    variant: 'default',
                    message:
                        referenceAmountLabel.value
                            ? `Loaded full product nutrition (~${referenceAmountLabel.value}${referenceUnitLabel.value}). Amount defaults to the whole item.`
                            : 'Loaded nutrition details. Amount defaults to the whole item.',
                };
                return;
            }

            const localMatch = foods.value.find((food) => food.barcode === trimmed);

            if (localMatch) {
                libraryForm.food_id = localMatch.id.toString();
                referenceAmountLabel.value = localMatch.serving_size
                    ? amountFormatter.format(localMatch.serving_size)
                    : null;
                referenceUnitLabel.value = localMatch.serving_unit ?? 'g';
                barcodeNotice.value = {
                    variant: 'default',
                    message: `Loaded ${localMatch.name} from your library.`,
                };
                return;
            }
        }

        barcodeNotice.value = {
            variant: 'destructive',
            message:
                'We could not find that barcode. Add it to your library so it is available next time.',
        };
    } catch (error) {
        console.error(error);
        barcodeNotice.value = {
            variant: 'destructive',
            message: 'Unable to look up the barcode. Enter it manually below.',
        };
    }

    manualForm.barcode = trimmed;
    referenceAmountLabel.value = null;
};

const toggleScanner = () => {
    scannerActive.value = !scannerActive.value;
    if (!scannerActive.value) {
        barcodeNotice.value = null;
    }
};

watch(
    () => summary.value.date.current,
    (value) => {
        selectedDateInput.value = value;
        libraryForm.consumed_on = value;
        manualForm.consumed_on = value;
        burnForm.recorded_on = value;
    },
    {immediate: true},
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Dashboard"/>

        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold">Daily nutrition overview</h1>
                    <p class="text-sm text-muted-foreground">
                        Track today’s intake, macros, and activity. Adjust your goals in
                        <Link
                            :href="nutritionSettingsRoute.url"
                            class="text-primary underline decoration-dotted underline-offset-4"
                        >
                            macro settings
                        </Link>
                        .
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        @click="goToPrevious"
                        aria-label="Previous day"
                    >
                        <ChevronLeft class="size-4"/>
                    </Button>

                    <div class="relative flex items-center gap-2">
                        <Calendar class="pointer-events-none absolute left-3 size-4 text-muted-foreground"/>
                        <Input
                            type="date"
                            v-model="selectedDateInput"
                            class="pl-9"
                            @change="navigateToDate(selectedDateInput)"
                        />
                    </div>

                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        @click="goToNext"
                        :disabled="summary.date.isToday"
                        aria-label="Next day"
                    >
                        <ChevronRight class="size-4"/>
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Calories consumed
                        </CardTitle>
                        <div class="text-3xl font-semibold">
                            {{ caloriesFormatter.format(summary.calories.consumed) }} kcal
                        </div>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        Includes every logged food entry for {{ summary.date.display }}.
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Calories burned
                        </CardTitle>
                        <div class="text-3xl font-semibold">
                            {{ caloriesFormatter.format(summary.calories.burned) }} kcal
                        </div>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        Logged activities reduce your daily net calories.
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Net calories
                        </CardTitle>
                        <div class="text-3xl font-semibold">
                            {{ caloriesFormatter.format(summary.calories.net) }} kcal
                        </div>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between text-sm text-muted-foreground">
                        <span>Goal</span>
                        <span v-if="summary.calories.goal !== null">
                            {{ caloriesFormatter.format(summary.calories.goal) }} kcal
                        </span>
                        <span v-else>—</span>
                    </CardContent>
                    <CardFooter v-if="summary.calories.goal !== null" class="text-sm text-muted-foreground">
                        {{ caloriesFormatter.format(summary.calories.remaining ?? 0) }} kcal remaining
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            Macro targets
                        </CardTitle>
                        <CardDescription v-if="summary.macro_goal">
                            {{ summary.macro_goal.protein_percentage }}% P ·
                            {{ summary.macro_goal.carb_percentage }}% C ·
                            {{ summary.macro_goal.fat_percentage }}% F
                        </CardDescription>
                        <CardDescription v-else>
                            Set macro goals to unlock per-macro guidance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent v-if="summary.macro_goal" class="space-y-1 text-sm text-muted-foreground">
                        <p>
                            Protein:
                            {{ gramsFormatter.format(summary.macro_goal.targets.protein) }}g ·
                            Carbs:
                            {{ gramsFormatter.format(summary.macro_goal.targets.carb) }}g ·
                            Fat:
                            {{ gramsFormatter.format(summary.macro_goal.targets.fat) }}g
                        </p>
                        <p>
                            Total daily goal:
                            {{ caloriesFormatter.format(summary.macro_goal.daily_calorie_goal) }} kcal
                        </p>
                    </CardContent>
                    <CardFooter class="text-sm">
                        <Link
                            :href="nutritionSettingsRoute.url"
                            class="inline-flex items-center gap-2 text-primary"
                        >
                            Update macros
                            <ArrowRight class="size-4"/>
                        </Link>
                    </CardFooter>
                </Card>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="macro in macroList" :key="macro.key">
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between text-sm font-medium text-muted-foreground">
                            {{ macro.label }}
                            <span v-if="macro.progress.goal" class="text-xs text-muted-foreground">
                                Goal: {{ gramsFormatter.format(macro.progress.goal) }}g
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex items-center gap-4">
                        <div class="relative h-24 w-24">
                            <svg class="h-full w-full" viewBox="0 0 100 100">
                                <circle
                                    class="text-muted stroke-current opacity-20"
                                    stroke-width="10"
                                    stroke="currentColor"
                                    fill="transparent"
                                    :r="macroCircleRadius"
                                    cx="50"
                                    cy="50"
                                />
                                <circle
                                    class="text-primary stroke-current"
                                    stroke-width="10"
                                    stroke-linecap="round"
                                    stroke="currentColor"
                                    fill="transparent"
                                    :r="macroCircleRadius"
                                    cx="50"
                                    cy="50"
                                    :style="macroCircleStyle(macro.progress.percentage)"
                                    transform="rotate(-90 50 50)"
                                />
                                <text
                                    x="50"
                                    y="54"
                                    text-anchor="middle"
                                    class="fill-foreground text-lg font-semibold"
                                >
                                    {{
                                        macro.progress.percentage !== null
                                            ? percentageFormatter.format(Math.min(macro.progress.percentage, 999.9))
                                            : '—'
                                    }}%
                                </text>
                            </svg>
                        </div>
                        <div class="space-y-2 text-sm">
                            <p class="text-foreground font-semibold">
                                {{ gramsFormatter.format(macro.progress.consumed) }}g consumed
                            </p>
                            <p v-if="macro.progress.remaining !== null" class="text-muted-foreground">
                                {{ gramsFormatter.format(macro.progress.remaining) }}g remaining
                            </p>
                            <p v-else class="text-muted-foreground">Set a goal to track remaining grams.</p>
                            <p
                                v-if="macro.progress.allowance > 0"
                                class="text-xs text-muted-foreground"
                            >
                                Includes
                                {{ gramsFormatter.format(macro.progress.allowance) }}g burn allowance
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Sheet>
                <SheetTrigger as-child>
                    <Button>
                    Log food
                    </Button>
                </SheetTrigger>
                <SheetContent side="bottom">
                    <SheetHeader>
                        <SheetTitle>
                            <span class="flex items-center gap-2">
                                <UtensilsCrossed class="size-5 text-primary"/>
                                Log intake
                            </span>
                        </SheetTitle>
                        <SheetDescription>
                            Scan a barcode or pick from your food library. You can also enter macros manually.
                        </SheetDescription>
                        <div class="pt-4">

                            <Tabs v-model="activeIntakeTab" class="w-full">
                                <TabsList class="grid w-full grid-cols-2">
                                    <TabsTrigger value="library">
                                        Library
                                    </TabsTrigger>
                                    <TabsTrigger value="manual">
                                        Manual
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>
                    </SheetHeader>
                    <div class="p-4 pt-0">
                        <div v-if="activeIntakeTab === 'library'" class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    class="w-full"
                                    type="button"
                                    variant="default"
                                    @click="toggleScanner"
                                >
                                    <Scan class="size-4"/>
                                    {{ scannerActive ? 'Stop scanning' : 'Scan barcode' }}
                                </Button>
                            </div>

                            <BarcodeScanner
                                :active="scannerActive"
                                @detected="handleBarcodeDetected"
                                @close="scannerActive = false"
                            />

                            <form @submit.prevent="submitLibrary" class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="food_id">Food item</Label>
                                    <select
                                        id="food_id"
                                        v-model="libraryForm.food_id"
                                        name="food_id"
                                        class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 h-10 rounded-md border bg-transparent px-3 text-sm outline-none transition-[color,box-shadow]"
                                        required
                                    >
                                        <option value="" disabled>Select a food</option>
                                        <option
                                            v-for="food in foods"
                                            :key="food.id"
                                            :value="food.id"
                                        >
                                            {{ food.name }}
                                            <span v-if="food.barcode">
                                                — {{ food.barcode }}
                                            </span>
                                        </option>
                                    </select>
                                    <InputError :message="libraryForm.errors.food_id"/>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="library_quantity">Number of servings</Label>
                                        <Input
                                            id="library_quantity"
                                            v-model="libraryForm.quantity"
                                            name="quantity"
                                            type="number"
                                            step="0.01"
                                            min="0.1"
                                            required
                                        />
                                        <InputError :message="libraryForm.errors.quantity"/>
                                        <p
                                            v-if="referenceAmountLabel"
                                            class="text-xs text-muted-foreground"
                                        >
                                            1 = whole item (≈ {{ referenceAmountLabel }}
                                            {{ referenceUnitLabel }}). Use decimals for partial
                                            amounts.
                                        </p>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="library_consumed_on">Date</Label>
                                        <Input
                                            id="library_consumed_on"
                                            v-model="libraryForm.consumed_on"
                                            name="consumed_on"
                                            type="date"
                                            required
                                        />
                                        <InputError :message="libraryForm.errors.consumed_on"/>
                                    </div>
                                </div>

                                <div
                                    v-if="libraryTotals"
                                    class="grid grid-cols-2 gap-3 rounded-md border border-dashed border-border p-3 text-sm text-muted-foreground md:grid-cols-4"
                                >
                                    <p><strong class="text-foreground">≈</strong> {{ caloriesFormatter.format(libraryTotals.calories) }} kcal</p>
                                    <p><strong class="text-foreground">Protein</strong> {{ gramsFormatter.format(libraryTotals.protein) }}g</p>
                                    <p><strong class="text-foreground">Carbs</strong> {{ gramsFormatter.format(libraryTotals.carbs) }}g</p>
                                    <p><strong class="text-foreground">Fat</strong> {{ gramsFormatter.format(libraryTotals.fat) }}g</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="submit"
                                        class="w-full"
                                        :disabled="libraryForm.processing"
                                    >
                                        <PlusCircle class="size-4"/>
                                        Log entry
                                    </Button>
                                    <InputError :message="libraryForm.errors.source"/>
                                </div>
                            </form>
                        </div>

                        <div v-else class="space-y-6">
                            <form @submit.prevent="submitManual" class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="manual_name">Food name</Label>
                                    <Input
                                        id="manual_name"
                                        v-model="manualForm.name"
                                        name="name"
                                        type="text"
                                        placeholder="Grilled chicken"
                                        required
                                    />
                                    <InputError :message="manualForm.errors.name"/>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="manual_quantity">Number of servings</Label>
                                        <Input
                                            id="manual_quantity"
                                            v-model="manualForm.quantity"
                                            name="quantity"
                                            type="number"
                                            step="0.01"
                                            min="0.1"
                                            required
                                        />
                                        <InputError :message="manualForm.errors.quantity"/>
                                        <p
                                            v-if="referenceAmountLabel"
                                            class="text-xs text-muted-foreground"
                                        >
                                            1 = whole item (≈ {{ referenceAmountLabel }}
                                            {{ referenceUnitLabel }}). Use decimals for partial
                                            amounts.
                                        </p>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="manual_serving_size_value">Serving size</Label>
                                        <div class="flex gap-2">
                                            <Input
                                                id="manual_serving_size_value"
                                                v-model="manualForm.serving_size_value"
                                                name="serving_size_value"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-full"
                                            />
                                            <select
                                                v-model="manualForm.serving_unit"
                                                name="serving_unit"
                                                class="border-input focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 h-10 rounded-md border bg-transparent px-3 text-sm outline-none transition-[color,box-shadow]"
                                            >
                                                <option
                                                    v-for="option in servingUnitOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <InputError :message="manualForm.errors.serving_unit"/>
                                    </div>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="grid gap-2">
                                        <Label for="manual_calories">Calories (per serving)</Label>
                                        <Input
                                            id="manual_calories"
                                            v-model="manualForm.calories"
                                            name="calories"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                        />
                                        <InputError :message="manualForm.errors.calories"/>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="manual_protein">Protein (g)</Label>
                                        <Input
                                            id="manual_protein"
                                            v-model="manualForm.protein_grams"
                                            name="protein_grams"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                        />
                                        <InputError :message="manualForm.errors.protein_grams"/>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="manual_carb">Carbs (g)</Label>
                                        <Input
                                            id="manual_carb"
                                            v-model="manualForm.carb_grams"
                                            name="carb_grams"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                        />
                                        <InputError :message="manualForm.errors.carb_grams"/>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="manual_fat">Fat (g)</Label>
                                        <Input
                                            id="manual_fat"
                                            v-model="manualForm.fat_grams"
                                            name="fat_grams"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                        />
                                        <InputError :message="manualForm.errors.fat_grams"/>
                                    </div>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="manual_consumed_on">Date</Label>
                                        <Input
                                            id="manual_consumed_on"
                                            v-model="manualForm.consumed_on"
                                            name="consumed_on"
                                            type="date"
                                            required
                                        />
                                        <InputError :message="manualForm.errors.consumed_on"/>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="manual_barcode">Barcode (optional)</Label>
                                        <Input
                                            id="manual_barcode"
                                            v-model="manualForm.barcode"
                                            name="barcode"
                                            type="text"
                                        />
                                        <InputError :message="manualForm.errors.barcode"/>
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-2 gap-3 rounded-md border border-dashed border-border p-3 text-sm text-muted-foreground md:grid-cols-4"
                                >
                                    <p><strong class="text-foreground">≈</strong> {{ caloriesFormatter.format(manualTotals.calories) }} kcal</p>
                                    <p><strong class="text-foreground">Protein</strong> {{ gramsFormatter.format(manualTotals.protein) }}g</p>
                                    <p><strong class="text-foreground">Carbs</strong> {{ gramsFormatter.format(manualTotals.carbs) }}g</p>
                                    <p><strong class="text-foreground">Fat</strong> {{ gramsFormatter.format(manualTotals.fat) }}g</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="submit"
                                        class="inline-flex items-center gap-2"
                                        :disabled="manualForm.processing"
                                    >
                                        <PlusCircle class="size-4"/>
                                        Log entry
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Flame class="size-5 text-primary"/>
                            Calories burned
                        </CardTitle>
                        <CardDescription>
                            Track exercise and adjustments to lower your net calories.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitBurn" class="grid gap-4 sm:grid-cols-2 sm:gap-6">
                            <div class="grid gap-2">
                                <Label for="burn_calories">Calories burned</Label>
                                <Input
                                    id="burn_calories"
                                    v-model="burnForm.calories"
                                    name="calories"
                                    type="number"
                                    min="0"
                                    required
                                />
                                <InputError :message="burnForm.errors.calories"/>
                            </div>
                            <div class="grid gap-2">
                                <Label for="burn_recorded">Date</Label>
                                <Input
                                    id="burn_recorded"
                                    v-model="burnForm.recorded_on"
                                    name="recorded_on"
                                    type="date"
                                    required
                                />
                                <InputError :message="burnForm.errors.recorded_on"/>
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="burn_description">Notes (optional)</Label>
                                <Input
                                    id="burn_description"
                                    v-model="burnForm.description"
                                    name="description"
                                    type="text"
                                    placeholder="Cycling, 30 minutes"
                                />
                                <InputError :message="burnForm.errors.description"/>
                            </div>
                            <div class="sm:col-span-2">
                                <Button
                                    type="submit"
                                    class="inline-flex items-center gap-2"
                                    :disabled="burnForm.processing"
                                >
                                    <Flame class="size-4"/>
                                    Log calories burned
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Weekly snapshot</CardTitle>
                        <CardDescription>
                            {{ summary.weekly.start }} → {{ summary.weekly.end }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-3 text-sm">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span>Calories</span>
                                <span class="text-foreground font-medium">
                                    {{ caloriesFormatter.format(summary.weekly.totals.calories) }} kcal
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span>Burned</span>
                                <span class="text-foreground font-medium">
                                    {{ caloriesFormatter.format(summary.weekly.totals.burned) }} kcal
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span>Net</span>
                                <span class="text-foreground font-medium">
                                    {{ caloriesFormatter.format(summary.weekly.totals.net) }} kcal
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="day in summary.weekly.days"
                                :key="day.date"
                                class="grid gap-1 rounded-md border border-border p-3 text-sm"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ day.weekday }}</span>
                                    <span class="text-muted-foreground">{{ day.date }}</span>
                                </div>
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span>Net</span>
                                    <span class="text-foreground font-medium">
                                        {{ caloriesFormatter.format(day.net) }} kcal
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <div class="h-1 flex-1 rounded-full bg-primary/10">
                                        <div
                                            class="h-1 rounded-full bg-primary"
                                            :style="{ width: `${Math.min(100, Math.abs(day.net) / 30)}%` }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Logged foods</CardTitle>
                        <CardDescription>
                            Foods consumed on {{ summary.date.display }}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="summary.entries.foods.length === 0" class="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No food entries for this date yet.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="entry in summary.entries.foods"
                                :key="entry.id"
                                class="flex flex-col gap-2 rounded-md border border-border p-3 text-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-foreground">
                                            {{ entry.name }}
                                        </p>
                                        <p class="text-muted-foreground">
                                            {{ entry.quantity }}
                                            {{ entry.serving_unit ?? 'g' }} ·
                                            {{ caloriesFormatter.format(entry.calories) }} kcal
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {{ entry.source === 'food' ? 'Library' : 'Manual' }}
                                    </Badge>
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-muted-foreground">
                                    <span>{{ gramsFormatter.format(entry.protein_grams) }}g protein</span>
                                    <span>{{ gramsFormatter.format(entry.carb_grams) }}g carbs</span>
                                    <span>{{ gramsFormatter.format(entry.fat_grams) }}g fat</span>
                                </div>

                                <div class="flex justify-end">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="inline-flex items-center gap-2 text-destructive"
                                        @click="removeFoodEntry(entry.id)"
                                    >
                                        <Trash2 class="size-4"/>
                                        Remove
                                    </Button>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Logged burns</CardTitle>
                        <CardDescription>
                            Activities recorded on {{ summary.date.display }}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="summary.entries.burns.length === 0" class="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No burn entries for this date yet.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="burn in summary.entries.burns"
                                :key="burn.id"
                                class="flex flex-col gap-2 rounded-md border border-border p-3 text-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-foreground">
                                            {{ burn.description || 'Activity' }}
                                        </p>
                                        <p class="text-muted-foreground">
                                            {{ caloriesFormatter.format(burn.calories) }} kcal · {{ burn.recorded_on }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="inline-flex items-center gap-2 text-destructive"
                                        @click="removeBurnEntry(burn.id)"
                                    >
                                        <Trash2 class="size-4"/>
                                        Remove
                                    </Button>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

        </div>
    </AppLayout>
</template>
