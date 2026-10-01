import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SanctionController::store
* @see app/Http/Controllers/Admin/SanctionController.php:27
* @route '/admin/users/{ulid}/ban'
*/
export const store = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/users/{ulid}/ban',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SanctionController::store
* @see app/Http/Controllers/Admin/SanctionController.php:27
* @route '/admin/users/{ulid}/ban'
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
* @see app/Http/Controllers/Admin/SanctionController.php:27
* @route '/admin/users/{ulid}/ban'
*/
store.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const ban = {
    store: Object.assign(store, store),
}

export default ban