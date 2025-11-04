<script setup lang="ts">
import FoodBarcodeController from '@/actions/App/Http/Controllers/FoodBarcodeController';
import FoodSearchController from '@/actions/App/Http/Controllers/FoodSearchController';
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
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {Input} from '@/components/ui/input';
import {Label} from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {Sheet, SheetClose, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger} from '@/components/ui/sheet';
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
    Search,
    Trash2,
    UtensilsCrossed,
} from 'lucide-vue-next';
import {computed, nextTick, ref, watch} from 'vue';
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

type NumericLike = number | string | null;

interface ExternalNutritionPayload {
    name?: string | null;
    barcode?: string | null;
    serving_size?: NumericLike;
    serving_unit?: string | null;
    servings?: NumericLike;
    calories?: NumericLike;
    calories_total?: NumericLike;
    protein?: NumericLike;
    protein_total?: NumericLike;
    carb?: NumericLike;
    carb_total?: NumericLike;
    fat?: NumericLike;
    fat_total?: NumericLike;
    default_quantity?: NumericLike;
    reference_quantity?: NumericLike;
    serving_quantity?: NumericLike;
    total_quantity?: NumericLike;
}

interface FoodSearchResult extends ExternalNutritionPayload {
    name: string;
    source?: string | null;
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
type WeeklyDay = Summary['weekly']['days'][number];

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

const macroKeys: MacroKey[] = ['protein', 'carb', 'fat'];

const macroLabel = (key: MacroKey): string => (key === 'carb' ? 'Carbs' : key.charAt(0).toUpperCase() + key.slice(1));

const macroList = computed(() =>
    macroKeys.map((key) => ({
        key,
        label: macroLabel(key),
        progress: summary.value.macros[key],
    })),
);

const macroStyles: Record<MacroKey, { accent: string; chip: string }> = {
    protein: {
        accent: 'text-emerald-500',
        chip: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600',
    },
    carb: {
        accent: 'text-sky-500',
        chip: 'border-sky-500/40 bg-sky-500/10 text-sky-600',
    },
    fat: {
        accent: 'text-amber-500',
        chip: 'border-amber-500/40 bg-amber-500/10 text-amber-600',
    },
};

const macroAccentClass = (key: MacroKey): string => macroStyles[key].accent;

const macroChipClass = (key: MacroKey): string => macroStyles[key].chip;

const macroCaloriesPerGram: Record<MacroKey, number> = {
    protein: 4,
    carb: 4,
    fat: 9,
};

const percentageFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
    minimumFractionDigits: 0,
});

const macroGoalPercentages = computed(() => {
    if (!summary.value.macro_goal) {
        return null;
    }

    return {
        protein: summary.value.macro_goal.protein_percentage,
        carb: summary.value.macro_goal.carb_percentage,
        fat: summary.value.macro_goal.fat_percentage,
    };
});

const macroGoalPercentage = (key: MacroKey): number | null =>
    macroGoalPercentages.value ? macroGoalPercentages.value[key] : null;

const calculateMacroPercentage = (calories: number, grams: number, key: MacroKey): number | null => {
    if (calories <= 0) {
        return null;
    }

    const macroCalories = grams * macroCaloriesPerGram[key];
    const percentage = (macroCalories / calories) * 100;

    if (!Number.isFinite(percentage)) {
        return null;
    }

    return Number.parseFloat(percentage.toFixed(1));
};

const weeklyMacroTotals = computed(() => {
    const weeklyCalories = summary.value.weekly.totals.calories;

    return macroKeys.map((key) => {
        const total = summary.value.weekly.totals[key];

        return {
            key,
            label: macroLabel(key),
            total,
            actualPercent: calculateMacroPercentage(weeklyCalories, total, key),
            goalPercent: macroGoalPercentage(key),
        };
    });
});

const dailyMacroPercentage = (day: WeeklyDay, key: MacroKey): number | null =>
    calculateMacroPercentage(day.calories, day.macros[key], key);

const formatPercentage = (value: number | null): string => {
    if (value === null) {
        return '--';
    }

    return `${percentageFormatter.format(value)}%`;
};

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

const amountFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 2,
});

const parseNumericValue = (value: unknown): number | null => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const numeric = Number(value);

        if (!Number.isNaN(numeric)) {
            return numeric;
        }
    }

    return null;
};

const formatNumericInput = (value: unknown): string => {
    const numeric = parseNumericValue(value);

    if (numeric === null) {
        return '';
    }

    return amountFormatter.format(numeric);
};

const SEARCH_LIMIT = 8;

const searchQuery = ref('');
const searchLoading = ref(false);
const searchResults = ref<FoodSearchResult[]>([]);
const searchError = ref<string | null>(null);
const searchPerformed = ref(false);
const searchAbortController = ref<AbortController | null>(null);
const manualSearchInput = ref<HTMLInputElement | null>(null);

const loadExternalNutrition = (
    food: ExternalNutritionPayload,
    options?: { barcode?: string | null },
): { referenceQuantity: number | null; servingUnit: string } => {
    activeIntakeTab.value = 'manual';

    manualForm.name = typeof food.name === 'string' ? food.name : '';

    const resolvedBarcode = options?.barcode ?? food.barcode;
    manualForm.barcode = typeof resolvedBarcode === 'string' ? resolvedBarcode : '';
    manualForm.quantity = '1';

    manualForm.serving_size_value = formatNumericInput(
        food.reference_quantity ?? food.serving_size ?? food.serving_quantity,
    );

    const unit = typeof food.serving_unit === 'string' && food.serving_unit.trim() !== ''
        ? food.serving_unit
        : 'g';

    manualForm.serving_unit = unit;
    manualForm.serving_unit_raw = unit;

    manualForm.calories = formatNumericInput(food.calories_total ?? food.calories);
    manualForm.protein_grams = formatNumericInput(food.protein_total ?? food.protein);
    manualForm.carb_grams = formatNumericInput(food.carb_total ?? food.carb);
    manualForm.fat_grams = formatNumericInput(food.fat_total ?? food.fat);

    const referenceQuantityNumeric = parseNumericValue(
        food.reference_quantity ?? food.serving_size ?? food.serving_quantity,
    );

    if (referenceQuantityNumeric !== null && referenceQuantityNumeric > 0) {
        referenceAmountLabel.value = amountFormatter.format(referenceQuantityNumeric);
    } else {
        referenceAmountLabel.value = null;
    }

    referenceUnitLabel.value = unit;

    return {
        referenceQuantity: referenceQuantityNumeric !== null && referenceQuantityNumeric > 0
            ? referenceQuantityNumeric
            : null,
        servingUnit: unit,
    };
};

const clearExternalSearch = () => {
    if (searchAbortController.value) {
        searchAbortController.value.abort();
        searchAbortController.value = null;
    }

    searchResults.value = [];
    searchError.value = null;
    searchPerformed.value = false;
};

const performExternalSearch = async () => {
    const query = searchQuery.value.trim();

    searchError.value = null;

    if (query.length < 2) {
        searchResults.value = [];
        searchPerformed.value = false;
        searchError.value = 'Enter at least 2 characters.';
        return;
    }

    if (searchAbortController.value) {
        searchAbortController.value.abort();
    }

    const controller = new AbortController();
    searchAbortController.value = controller;
    searchLoading.value = true;
    searchPerformed.value = true;

    try {
        const response = await fetch(
            FoodSearchController({
                query: {
                    query,
                    limit: SEARCH_LIMIT,
                },
            }).url,
            {
                headers: {
                    Accept: 'application/json',
                },
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            throw new Error('Search failed');
        }

        const data = await response.json();
        const products: FoodSearchResult[] = Array.isArray(data.results)
            ? data.results.filter(
                (item: unknown): item is FoodSearchResult =>
                    typeof item === 'object' &&
                    item !== null &&
                    'name' in item &&
                    typeof (item as { name: unknown }).name === 'string',
            )
            : [];

        searchResults.value = products;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        console.error(error);
        searchError.value = 'Unable to search right now. Try again in a moment.';
        searchResults.value = [];
    } finally {
        if (searchAbortController.value === controller) {
            searchLoading.value = false;
            searchAbortController.value = null;
        }
    }
};

const applySearchResult = (result: FoodSearchResult) => {
    const { referenceQuantity, servingUnit } = loadExternalNutrition(result, {
        barcode: typeof result.barcode === 'string' ? result.barcode : null,
    });

    const sizeLabel = referenceQuantity
        ? ` (~${amountFormatter.format(referenceQuantity)}${servingUnit})`
        : '';

    barcodeNotice.value = {
        variant: 'default',
        message: `Loaded ${result.name} from Open Food Facts${sizeLabel}. Amount defaults to the whole item.`,
    };
};

const resultMacroSummary = (result: FoodSearchResult): string => {
    const protein = parseNumericValue(result.protein ?? result.protein_total);
    const carbs = parseNumericValue(result.carb ?? result.carb_total);
    const fat = parseNumericValue(result.fat ?? result.fat_total);

    const parts: string[] = [];

    if (protein !== null && protein > 0) {
        parts.push(`${gramsFormatter.format(protein)}g protein`);
    }

    if (carbs !== null && carbs > 0) {
        parts.push(`${gramsFormatter.format(carbs)}g carbs`);
    }

    if (fat !== null && fat > 0) {
        parts.push(`${gramsFormatter.format(fat)}g fat`);
    }

    return parts.join(' · ');
};

const resultCalories = (result: FoodSearchResult): number | null => {
    const calories = parseNumericValue(result.calories ?? result.calories_total);

    if (calories !== null && calories > 0) {
        return calories;
    }

    return null;
};

const openSearchPanel = () => {
    scannerActive.value = false;
    activeIntakeTab.value = 'manual';

    nextTick(() => {
        manualSearchInput.value?.focus();
    });
};

const macroCircleRadius = 42;
const macroCircleCircumference = 2 * Math.PI * macroCircleRadius;

interface CircleStyle {
    strokeDasharray: string;
    strokeDashoffset: number;
}

const macroCircleStyle = (percentage: number | null): CircleStyle => {
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

const summaryCircleRadius = 48;
const summaryCircleCircumference = 2 * Math.PI * summaryCircleRadius;

const summaryCircleStyle = (percentage: number | null): CircleStyle => {
    const circumference = summaryCircleCircumference;

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

const calorieGoal = computed(() => {
    if (summary.value.calories.goal !== null) {
        return summary.value.calories.goal;
    }

    return summary.value.macro_goal?.daily_calorie_goal ?? null;
});

const summaryProgressPercentage = computed(() => {
    const goal = calorieGoal.value;

    if (!goal || goal <= 0) {
        return null;
    }

    const net = summary.value.calories.net;
    const clampedNet = Math.max(net, 0);

    return Math.min((clampedNet / goal) * 100, 100);
});

const summaryCircleCalories = computed(() => {
    const remaining = summary.value.calories.remaining;

    if (remaining === null) {
        return null;
    }

    return remaining;
});

const summaryCircleDisplay = computed(() => {
    const calories = summaryCircleCalories.value;

    if (calories === null) {
        return '--';
    }

    return caloriesFormatter.format(calories);
});

const summaryRemainingLabel = computed(() => {
    const goal = calorieGoal.value;

    if (!goal || goal <= 0) {
        return 'Set a calorie goal to track daily progress.';
    }

    const remaining = summary.value.calories.remaining;

    if (remaining === null) {
        return 'Goal data unavailable.';
    }

    if (remaining > 0) {
        return `${caloriesFormatter.format(remaining)} kcal remaining`;
    }

    if (remaining < 0) {
        return `${caloriesFormatter.format(Math.abs(remaining))} kcal over goal`;
    }

    return 'Goal met for today';
});

const summaryRemainingClass = computed(() => {
    const goal = calorieGoal.value;

    if (!goal || goal <= 0) {
        return 'text-muted-foreground';
    }

    const remaining = summary.value.calories.remaining;

    if (remaining === null) {
        return 'text-muted-foreground';
    }

    if (remaining < 0) {
        return 'text-destructive';
    }

    if (remaining === 0) {
        return 'text-emerald-600';
    }

    return 'text-muted-foreground';
});

const summaryGoalLabel = computed(() => {
    const goal = calorieGoal.value;

    if (!goal || goal <= 0) {
        return 'Not set';
    }

    return `${caloriesFormatter.format(goal)} kcal`;
});

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
                clearExternalSearch();
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
                const { referenceQuantity, servingUnit } = loadExternalNutrition(data.food, {
                    barcode: trimmed,
                });

                const message = referenceQuantity
                    ? `Loaded full product nutrition (~${amountFormatter.format(referenceQuantity)}${servingUnit}). Amount defaults to the whole item.`
                    : 'Loaded nutrition details. Amount defaults to the whole item.';

                barcodeNotice.value = {
                    variant: 'default',
                    message,
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

            <Card>
                <CardHeader class="space-y-2">
                    <CardTitle class="text-lg font-semibold">Daily summary</CardTitle>
                    <CardDescription>
                        How today’s intake stacks up for {{ summary.date.display }}.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:gap-12">
                        <div class="flex flex-col items-center gap-6 text-center lg:items-start lg:text-left">
                            <div class="relative h-40 w-40 p-2">
                                <svg class="h-full w-full" viewBox="0 0 100 100">
                                    <circle
                                        class="stroke-slate-300 text-slate-300"
                                        stroke-width="5"
                                        stroke="currentColor"
                                        fill="transparent"
                                        :r="summaryCircleRadius"
                                        cx="50"
                                        cy="50"
                                    />
                                    <circle
                                        v-if="summaryProgressPercentage !== null"
                                        class="stroke-current text-primary transition-all"
                                        stroke-width="5"
                                        stroke-linecap="round"
                                        stroke="currentColor"
                                        fill="transparent"
                                        :r="summaryCircleRadius"
                                        cx="50"
                                        cy="50"
                                        :style="summaryCircleStyle(summaryProgressPercentage)"
                                        transform="rotate(-90 50 50)"
                                    />
                                    <text
                                        x="50"
                                        y="50"
                                        text-anchor="middle"
                                        class="text-xl font-semibold text-foreground"
                                    >
                                        {{ summaryCircleDisplay }}
                                    </text>
                                    <text
                                        x="50"
                                        y="66"
                                        text-anchor="middle"
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        kcal
                                    </text>
                                </svg>
                            </div>
                            <div class="grid w-full max-w-sm gap-3 text-sm">
                                <div
                                    class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/20 px-4 py-3"
                                >
                                    <span class="flex items-center gap-2 text-muted-foreground">
                                        <UtensilsCrossed class="size-4"/>
                                        Consumed
                                    </span>
                                    <span class="font-semibold text-foreground">
                                        {{ caloriesFormatter.format(summary.calories.consumed) }} kcal
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/20 px-4 py-3"
                                >
                                    <span class="flex items-center gap-2 text-muted-foreground">
                                        <Flame class="size-4"/>
                                        Burned
                                    </span>
                                    <span class="font-semibold text-foreground">
                                        {{ caloriesFormatter.format(summary.calories.burned) }} kcal
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/20 px-4 py-3"
                                >
                                    <span class="text-muted-foreground">Goal</span>
                                    <span class="font-semibold text-foreground">
                                        {{ summaryGoalLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="space-y-2">
                    <CardTitle class="text-lg font-semibold">Macronutrients</CardTitle>
                    <CardDescription>
                        Protein, carb, and fat progress for {{ summary.date.display }}.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                        <div
                            v-for="macro in macroList"
                            :key="macro.key"
                            class="space-y-0"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm w-full text-center font-medium text-muted-foreground">
                                    {{ macro.label }}
                                </p>
                            </div>
                            <div class="flex flex-col items-center gap-0 text-center">
                                <div class="relative h-32 w-32">
                                    <svg class="h-full w-full" viewBox="0 0 100 100">
                                        <circle
                                            class="stroke-slate-200 text-slate-200"
                                            stroke-width="5"
                                            stroke="currentColor"
                                            fill="transparent"
                                            :r="macroCircleRadius"
                                            cx="50"
                                            cy="50"
                                        />
                                        <circle
                                            :class="['stroke-current', macroAccentClass(macro.key)]"
                                            stroke-width="5"
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
                                            y="50"
                                            text-anchor="middle"
                                            :class="['text-xl font-semibold fill-current text-slate-600']"
                                        >
                                            {{ gramsFormatter.format(macro.progress.consumed) }}g
                                        </text>
                                        <text
                                            v-if="macro.progress.goal !== null"
                                            x="50"
                                            y="68"
                                            text-anchor="middle"
                                            class="text-xs font-medium text-slate-600 opacity-50"
                                        >
                                            / {{ gramsFormatter.format(macro.progress.goal) }}g
                                        </text>
                                        <text
                                            v-else
                                            x="50"
                                            y="66"
                                            text-anchor="middle"
                                            class="text-xs font-medium text-muted-foreground"
                                        >
                                            Goal not set
                                        </text>
                                    </svg>
                                </div>
                                <p v-if="macro.progress.remaining !== null" class="text-sm text-muted-foreground">
                                    {{ gramsFormatter.format(macro.progress.remaining) }}g left
                                </p>
                                <p v-else class="text-sm text-muted-foreground">
                                    Set a goal to track remaining grams.
                                </p>
                                <p
                                    v-if="macro.progress.allowance > 0"
                                    :class="['text-xs mt-1', macroAccentClass(macro.key)]"
                                >
                                    Includes {{ gramsFormatter.format(macro.progress.allowance) }}g burn allowance.
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

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
                                <Button
                                    class="w-full"
                                    type="button"
                                    variant="secondary"
                                    @click="openSearchPanel"
                                >
                                    <Search class="size-4"/>
                                    Search foods
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
                                    <SheetClose as-child>
                                        <Button
                                            type="submit"
                                            class="w-full"
                                            :disabled="libraryForm.processing"
                                        >
                                            <PlusCircle class="size-4"/>
                                            Log entry
                                        </Button>
                                    </SheetClose>
                                    <InputError :message="libraryForm.errors.source"/>
                                </div>
                            </form>
                        </div>

                        <div v-else class="space-y-6">
                            <div class="grid gap-3 rounded-lg border border-border/60 bg-muted/10 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div class="flex-1 space-y-2">
                                        <Label for="openfood_search">Search Open Food Facts</Label>
                                        <Input
                                            id="openfood_search"
                                            ref="manualSearchInput"
                                            v-model="searchQuery"
                                            name="openfood_search"
                                            type="search"
                                            placeholder="e.g. Greek yogurt"
                                            @keydown.enter.prevent="performExternalSearch"
                                        />
                                        <p class="text-xs text-muted-foreground">
                                            Find nutrition details when you do not have a barcode. Powered by Open Food Facts.
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            class="sm:self-auto"
                                            :disabled="searchLoading"
                                            @click="performExternalSearch"
                                        >
                                            {{ searchLoading ? 'Searching…' : 'Search' }}
                                        </Button>
                                        <Button
                                            v-if="searchResults.length || searchPerformed"
                                            type="button"
                                            variant="ghost"
                                            class="text-xs"
                                            :disabled="searchLoading"
                                            @click="clearExternalSearch"
                                        >
                                            Clear
                                        </Button>
                                    </div>
                                </div>

                                <p v-if="searchError" class="text-sm text-destructive">
                                    {{ searchError }}
                                </p>
                                <p v-else-if="searchLoading" class="text-sm text-muted-foreground">
                                    Searching Open Food Facts…
                                </p>
                                <ul
                                    v-else-if="searchResults.length"
                                    class="grid max-h-60 gap-2 overflow-y-auto pr-1"
                                >
                                    <li
                                        v-for="result in searchResults"
                                        :key="result.barcode ?? result.name"
                                    >
                                        <button
                                            type="button"
                                            class="w-full rounded-md border border-border/60 bg-background/80 px-3 py-2 text-left transition hover:border-primary/60 hover:bg-primary/5"
                                            @click="applySearchResult(result)"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="space-y-1">
                                                    <p class="text-sm font-medium text-foreground">
                                                        {{ result.name }}
                                                    </p>
                                                    <p class="text-xs text-muted-foreground flex flex-wrap items-center gap-1">
                                                        <span v-if="resultCalories(result) !== null">
                                                            ≈ {{ caloriesFormatter.format(resultCalories(result) ?? 0) }} kcal
                                                        </span>
                                                        <span
                                                            v-if="resultCalories(result) !== null && resultMacroSummary(result)"
                                                            aria-hidden="true"
                                                        >
                                                            ·
                                                        </span>
                                                        <span v-if="resultMacroSummary(result)">
                                                            {{ resultMacroSummary(result) }}
                                                        </span>
                                                    </p>
                                                    <p class="text-xs text-muted-foreground">
                                                        Serving:
                                                        {{
                                                            formatNumericInput(
                                                                result.serving_size ??
                                                                    result.reference_quantity ??
                                                                    result.serving_quantity
                                                            ) || 'n/a'
                                                        }}
                                                        {{ result.serving_unit ?? 'g' }}
                                                        <span v-if="result.barcode" class="ml-2">
                                                            • {{ result.barcode }}
                                                        </span>
                                                    </p>
                                                </div>
                                                <span class="text-xs font-medium text-primary">Use</span>
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                                <p v-else-if="searchPerformed" class="text-sm text-muted-foreground">
                                    No matches found. Try different keywords.
                                </p>
                            </div>

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

            <Sheet>
                <SheetTrigger as-child>
                    <Button
                        type="button"
                        class="inline-flex items-center gap-2"
                    >
                        <Flame class="size-4"/>
                        Log calories burned
                    </Button>
                </SheetTrigger>
                <SheetContent side="bottom">
                    <SheetHeader>
                        <SheetTitle>
                                        <span class="flex items-center gap-2">
                                            <Flame class="size-5 text-primary"/>
                                            Log calories burned
                                        </span>
                        </SheetTitle>
                        <SheetDescription>
                            Record workouts or other adjustments that reduce your net calories.
                        </SheetDescription>
                    </SheetHeader>
                    <div class="p-4 pt-0">
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
                            <div class="sm:col-span-2 flex items-center gap-3">
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
                    </div>
                </SheetContent>
            </Sheet>

            <div class="grid gap-4 lg:grid-cols-3">
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

                        <div class="space-y-2 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Macro totals</p>
                            <div class="space-y-2">
                                <div
                                    v-for="macro in weeklyMacroTotals"
                                    :key="macro.key"
                                    class="flex items-center justify-between rounded-md border border-border/70 bg-muted/10 px-3 py-2"
                                >
                                    <div class="space-y-1">
                                        <p class="font-medium text-foreground">{{ macro.label }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatPercentage(macro.actualPercent) }}
                                            of calories
                                            <template v-if="macro.goalPercent !== null">
                                                · goal {{ formatPercentage(macro.goalPercent) }}
                                            </template>
                                        </p>
                                    </div>
                                    <span class="text-foreground font-semibold">
                                        {{ gramsFormatter.format(macro.total) }} g
                                    </span>
                                </div>
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
                                <div class="flex flex-wrap gap-2 pt-2">
                                    <div
                                        v-for="macroKey in macroKeys"
                                        :key="`${day.date}-${macroKey}`"
                                        :class="[
                                            'inline-flex min-w-[140px] items-center justify-between gap-3 rounded-full border px-3 py-2 text-xs',
                                            macroChipClass(macroKey),
                                        ]"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold leading-none">
                                                {{ macroLabel(macroKey) }}
                                            </span>
                                            <span class="text-[11px] font-medium text-muted-foreground">
                                                {{ formatPercentage(dailyMacroPercentage(day, macroKey)) }}
                                                <template v-if="macroGoalPercentage(macroKey) !== null">
                                                    · goal {{ formatPercentage(macroGoalPercentage(macroKey)) }}
                                                </template>
                                            </span>
                                        </div>
                                        <span class="text-sm font-semibold">
                                            {{ gramsFormatter.format(day.macros[macroKey]) }}g
                                        </span>
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
