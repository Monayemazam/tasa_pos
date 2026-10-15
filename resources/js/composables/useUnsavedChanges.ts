import { onMounted, onUnmounted, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Warns users when leaving a page with unsaved modifications (Prompt 16 §4).
 * Handles both browser refresh/close (beforeunload) and Inertia page transitions.
 */
export function useUnsavedChanges(isDirty: Ref<boolean>, message = 'You have unsaved changes. Are you sure you want to leave this page?') {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
        if (isDirty.value) {
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    };

    let removeInertiaListener: (() => void) | null = null;

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload);

        // Inertia router navigation hook
        removeInertiaListener = router.on('before', (event) => {
            if (isDirty.value) {
                const confirmed = window.confirm(message);
                if (!confirmed) {
                    event.preventDefault();
                }
            }
        });
    });

    onUnmounted(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload);
        if (removeInertiaListener) {
            removeInertiaListener();
        }
    });
}
