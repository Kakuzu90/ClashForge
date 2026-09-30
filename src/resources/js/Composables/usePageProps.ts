import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Typed access to the shared props every page receives (App\Http\Data\SharedPropsData). */
export function usePageProps() {
    const page = usePage();

    return {
        auth: computed(() => page.props.auth),
        can: computed(() => page.props.auth?.can ?? {}),
        flash: computed(() => page.props.flash),
        unreadCount: computed(() => page.props.unreadCount),
        url: computed(() => page.url),
    };
}
