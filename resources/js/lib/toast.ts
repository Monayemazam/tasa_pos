import { ref } from 'vue';
import { type BadgeTone } from '@/components/tasa/StatusBadge.vue';

export interface ToastItem {
    id: string;
    message: string;
    tone?: BadgeTone;
    actionLabel?: string;
    onAction?: () => void;
    duration?: number;
}

export const toasts = ref<ToastItem[]>([]);

export function showToast(
    message: string,
    tone: BadgeTone = 'info',
    options?: { actionLabel?: string; onAction?: () => void; duration?: number }
) {
    const id = Math.random().toString(36).substring(2, 9);
    const duration = options?.duration ?? (tone === 'danger' ? 0 : 4000);

    const toast: ToastItem = {
        id,
        message,
        tone,
        actionLabel: options?.actionLabel,
        onAction: options?.onAction,
        duration,
    };

    toasts.value.push(toast);

    if (duration > 0) {
        setTimeout(() => {
            removeToast(id);
        }, duration);
    }

    return id;
}

export function removeToast(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}
