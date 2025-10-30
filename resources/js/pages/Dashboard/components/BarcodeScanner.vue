<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';

const emits = defineEmits<{
    (e: 'detected', barcode: string): void;
    (e: 'close'): void;
}>();

const props = withDefaults(
    defineProps<{
        active: boolean;
    }>(),
    {
        active: false,
    },
);

const videoRef = ref<HTMLVideoElement | null>(null);
const errorMessage = ref<string | null>(null);
const isSupported = typeof window !== 'undefined' && 'BarcodeDetector' in window;
let stream: MediaStream | null = null;
let detector: BarcodeDetector | null = null;
let animationFrame: number | null = null;

const stop = () => {
    if (animationFrame !== null) {
        cancelAnimationFrame(animationFrame);
        animationFrame = null;
    }

    stream?.getTracks().forEach((track) => track.stop());
    stream = null;

    emits('close');
};

const detect = async () => {
    if (!props.active || detector === null || videoRef.value === null) {
        return;
    }

    try {
        const barcodes = await detector.detect(videoRef.value);

        if (barcodes.length > 0) {
            const [first] = barcodes;

            if (first.rawValue) {
                emits('detected', first.rawValue);
                stop();
                return;
            }
        }
    } catch (error) {
        console.error(error);
        errorMessage.value =
            'Unable to read from the camera. Please try manual entry.';
        stop();
        return;
    }

    animationFrame = requestAnimationFrame(detect);
};

const start = async () => {
    if (!isSupported || stream !== null) {
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
            },
        });

        if (videoRef.value === null) {
            return;
        }

        videoRef.value.srcObject = stream;
        await videoRef.value.play();

        detector = new BarcodeDetector({
            formats: ['ean_13', 'ean_8', 'code_128', 'upc_a', 'upc_e'],
        });

        animationFrame = requestAnimationFrame(detect);
    } catch (error) {
        console.error(error);
        errorMessage.value =
            'Camera access was denied. Allow camera access or enter the barcode manually.';
        stop();
    }
};

watch(
    () => props.active,
    (active) => {
        if (!isSupported) {
            return;
        }

        if (active) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stop();
});
</script>

<template>
    <div class="grid gap-4">
        <p v-if="!isSupported" class="text-sm text-muted-foreground">
            Barcode scanning is not supported in this browser. Enter the
            barcode manually below.
        </p>

        <div v-else class="grid gap-2">
            <div
                v-if="active"
                class="relative overflow-hidden rounded-lg border border-border"
            >
                <video
                    ref="videoRef"
                    playsinline
                    muted
                    class="aspect-video w-full object-cover"
                />

                <div
                    class="pointer-events-none absolute inset-0 border border-dashed border-primary"
                />
            </div>

            <p v-if="errorMessage" class="text-sm text-destructive">
                {{ errorMessage }}
            </p>
        </div>
    </div>
</template>
