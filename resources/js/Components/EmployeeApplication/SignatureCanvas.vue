<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const canvasRef = ref(null);
const drawing = ref(false);
const hasStroke = ref(false);

let ctx = null;

const setupCanvas = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * window.devicePixelRatio;
    canvas.height = rect.height * window.devicePixelRatio;
    ctx = canvas.getContext('2d');
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
    ctx.strokeStyle = '#1e293b';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
};

const getPos = (e) => {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches?.[0];
    const clientX = touch ? touch.clientX : e.clientX;
    const clientY = touch ? touch.clientY : e.clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
};

const startDraw = (e) => {
    e.preventDefault();
    drawing.value = true;
    const { x, y } = getPos(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
};

const draw = (e) => {
    if (!drawing.value) return;
    e.preventDefault();
    hasStroke.value = true;
    const { x, y } = getPos(e);
    ctx.lineTo(x, y);
    ctx.stroke();
};

const endDraw = () => {
    if (!drawing.value) return;
    drawing.value = false;
    exportSignature();
};

const exportSignature = () => {
    if (!hasStroke.value || !canvasRef.value) {
        emit('update:modelValue', '');
        return;
    }
    emit('update:modelValue', canvasRef.value.toDataURL('image/png'));
};

const clear = () => {
    if (!ctx || !canvasRef.value) return;
    const canvas = canvasRef.value;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasStroke.value = false;
    emit('update:modelValue', '');
};

onMounted(() => {
    setupCanvas();
    window.addEventListener('resize', setupCanvas);
});

watch(
    () => props.modelValue,
    (val) => {
        if (!val) clear();
    },
);
</script>

<template>
    <div>
        <p class="mb-2 text-sm text-slate-600">Firma con el dedo o el ratón en el recuadro.</p>
        <div class="overflow-hidden rounded-lg border-2 border-dashed border-slate-300 bg-white">
            <canvas
                ref="canvasRef"
                class="h-40 w-full touch-none sm:h-48"
                @mousedown="startDraw"
                @mousemove="draw"
                @mouseup="endDraw"
                @mouseleave="endDraw"
                @touchstart="startDraw"
                @touchmove="draw"
                @touchend="endDraw"
            />
        </div>
        <button
            type="button"
            class="mt-2 text-sm text-slate-600 underline hover:text-slate-900"
            @click="clear"
        >
            Borrar firma
        </button>
    </div>
</template>
