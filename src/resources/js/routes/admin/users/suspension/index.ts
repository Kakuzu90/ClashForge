import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SanctionController::store
* @see app/Http/Controllers/Admin/SanctionController.php:22
* @route '/admin/users/{ulid}/suspension'
*/
export const store = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/users/{ulid}/suspension',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SanctionController::store
* @see app/Http/Controllers/Admin/SanctionController.php:22
* @route '/admin/users/{ulid}/suspension'
*/
store.url = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ulid: args }
    }

    if (Array.isArray(args)) {
        args = {
            ulid: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ulid: args.ulid,
    }

    return store.definition.url
            .replace('{ulid}', parsedArgs.ulid.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SanctionController::store
* @see app/Http/Controllers/Admin/SanctionController.php:22
* @route '/admin/users/{ulid}/suspension'
*/
store.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const suspension = {
    store: Object.assign(store, store),
}

export default suspension