import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Accounts\VerificationController::store
* @see app/Http/Controllers/Accounts/VerificationController.php:43
* @route '/accounts/{ulid}/verify'
*/
export const store = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/accounts/{ulid}/verify',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Accounts\VerificationController::store
* @see app/Http/Controllers/Accounts/VerificationController.php:43
* @route '/accounts/{ulid}/verify'
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
* @see \App\Http\Controllers\Accounts\VerificationController::store
* @see app/Http/Controllers/Accounts/VerificationController.php:43
* @route '/accounts/{ulid}/verify'
*/
store.post = (args: { ulid: string | number } | [ulid: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const verify = {
    store: Object.assign(store, store),
}

export default verify